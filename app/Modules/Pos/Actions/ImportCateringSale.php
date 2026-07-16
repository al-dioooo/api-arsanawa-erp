<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Events\SaleImported;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Models\SalePayment;
use App\Modules\Pos\Services\CheckoutService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportCateringSale
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    /**
     * Write contract for other modules: upsert one imported catering order.
     *
     * Matches an existing sale on its import source and external reference, so
     * re-importing the same order updates it rather than duplicating it.
     *
     * @param  array{
     *     external_reference: string,
     *     source_channel: string,
     *     branch_id: int|null,
     *     register_id: int|null,
     *     partner_id: int,
     *     customer_name: string,
     *     order_date: string,
     *     fulfilment_date: mixed,
     *     fulfilment_time_window: string|null,
     *     delivery_address: string|null,
     *     notes: string|null,
     *     payment_method: string|null,
     *     payment_reference: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $data
     * @return bool True when a sale was created, false when one was updated.
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, ?int $userId, array $data): bool
    {
        $calculations = $this->checkoutService->calculate($companyId, $data['lines'], $data['order_date']);
        $paymentMethod = $data['payment_method'];

        $sale = Sale::query()
            ->forCompany($companyId)
            ->where('source', 'import')
            ->where('source_channel', $data['source_channel'])
            ->where('external_reference', $data['external_reference'])
            ->first();
        $exists = $sale !== null;

        if (! $sale) {
            $sale = new Sale([
                'company_id' => $companyId,
                'sale_number' => 'IMP-'.date('Ymd').'-'.strtoupper(Str::random(6)),
                'source' => 'import',
                'source_channel' => $data['source_channel'],
                'external_reference' => $data['external_reference'],
                'created_by' => $userId,
            ]);
        }

        $sale->fill([
            'branch_id' => $data['branch_id'],
            'register_id' => $data['register_id'],
            'cashier_shift_id' => null,
            'type' => 'catering',
            'partner_id' => $data['partner_id'],
            'customer_name' => $data['customer_name'],
            'status' => 'confirmed',
            'order_date' => $data['order_date'],
            'fulfilment_date' => $data['fulfilment_date'],
            'fulfilment_time_window' => $data['fulfilment_time_window'],
            'delivery_address' => $data['delivery_address'],
            'currency_id' => 1,
            'exchange_rate' => 1,
            'subtotal' => $calculations['subtotal'],
            'discount_total' => $calculations['discount_total'],
            'tax_total' => $calculations['tax_total'],
            'total' => $calculations['total'],
            'amount_paid' => $paymentMethod ? $calculations['total'] : '0.0000',
            'notes' => $data['notes'],
            'updated_by' => $userId,
        ]);
        $sale->save();
        $sale->lines()->delete();

        foreach ($calculations['lines'] as $line) {
            SaleLine::query()->create(array_merge($line, ['sale_id' => $sale->id]));
        }

        if ($data['source_channel'] === 'google_form') {
            $sale->payments()->delete();

            if ($paymentMethod) {
                SalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'method' => $paymentMethod,
                    'amount' => $calculations['total'],
                    'reference' => $data['payment_reference'],
                    'paid_at' => $data['order_date'],
                    'created_by' => $userId,
                ]);
            }
        }

        if (! $exists) {
            // Triggers a WhatsApp receipt; the listener defers the actual
            // send until the caller's transaction commits.
            event(new SaleImported($sale->id));
        }

        return ! $exists;
    }
}

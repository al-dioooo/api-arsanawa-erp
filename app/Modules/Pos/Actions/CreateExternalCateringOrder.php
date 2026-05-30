<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Partners\Actions\FindOrCreateCustomer;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateExternalCateringOrder
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly FindOrCreateCustomer $findOrCreateCustomer,
    ) {}

    /**
     * @param  array{id: int, source_channel: string, created_by?: int|null}  $apiKey
     * @param  array<string, mixed>  $data
     * @return array{sale: Sale, created: bool}
     */
    public function execute(int $companyId, array $apiKey, array $data): array
    {
        $existing = Sale::query()
            ->forCompany($companyId)
            ->where('source', 'external')
            ->where('source_channel', $apiKey['source_channel'])
            ->where('external_reference', $data['external_reference'])
            ->first();

        if ($existing) {
            return [
                'sale' => $existing->load(['lines', 'payments', 'promotions', 'register']),
                'created' => false,
            ];
        }

        $orderDate = $data['order_date'] ?? now()->toDateString();
        $calculations = $this->checkoutService->calculate($companyId, $data['lines'], $orderDate);
        $customer = $this->findOrCreateCustomer->execute($companyId, $apiKey['created_by'] ?? null, $data['customer']);

        return DB::transaction(function () use ($companyId, $apiKey, $data, $orderDate, $calculations, $customer): array {
            $sale = Sale::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'register_id' => null,
                'cashier_shift_id' => null,
                'sale_number' => 'EXT-'.date('Ymd').'-'.strtoupper(Str::random(6)),
                'type' => 'catering',
                'partner_id' => $customer['id'],
                'customer_name' => $data['customer']['name'],
                'status' => 'draft',
                'source' => 'external',
                'source_channel' => $apiKey['source_channel'],
                'external_reference' => $data['external_reference'],
                'external_api_key_id' => $apiKey['id'],
                'order_date' => $orderDate,
                'fulfilment_date' => $data['fulfilment_date'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'currency_id' => $data['currency_id'] ?? 1,
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'subtotal' => $calculations['subtotal'],
                'discount_total' => $calculations['discount_total'],
                'tax_total' => $calculations['tax_total'],
                'total' => $calculations['total'],
                'amount_paid' => '0.0000',
                'notes' => $data['notes'] ?? null,
                'created_by' => $apiKey['created_by'] ?? null,
                'updated_by' => $apiKey['created_by'] ?? null,
            ]);

            foreach ($calculations['lines'] as $line) {
                SaleLine::query()->create(array_merge($line, ['sale_id' => $sale->id]));
            }

            return [
                'sale' => $sale->load(['lines', 'payments', 'promotions', 'register']),
                'created' => true,
            ];
        });
    }
}

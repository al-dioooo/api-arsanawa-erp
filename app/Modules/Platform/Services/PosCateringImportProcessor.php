<?php

namespace App\Modules\Platform\Services;

use App\Modules\Inventory\Models\ProductVariant;
use App\Modules\Partners\Actions\FindOrCreateCustomer;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Services\CheckoutService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosCateringImportProcessor
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly FindOrCreateCustomer $findOrCreateCustomer,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, list<string>>
     */
    public function validateRow(int $companyId, array $row): array
    {
        $errors = [];

        foreach (['order_reference', 'branch_code', 'customer_name', 'fulfilment_date', 'sku', 'quantity'] as $field) {
            if (($row[$field] ?? '') === '') {
                $errors[$field][] = __('This field is required.');
            }
        }

        if (($row['branch_code'] ?? '') !== '' && ! DB::table('branches')->where('company_id', $companyId)->where('code', $row['branch_code'])->exists()) {
            $errors['branch_code'][] = __('Branch code was not found.');
        }

        if (($row['sku'] ?? '') !== '' && ! ProductVariant::query()->forCompany($companyId)->where('sku', $row['sku'])->exists()) {
            $errors['sku'][] = __('SKU was not found.');
        }

        if (($row['quantity'] ?? 0) <= 0) {
            $errors['quantity'][] = __('Quantity must be greater than zero.');
        }

        return $errors;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int}
     */
    public function commit(int $companyId, ?int $userId, Collection $rows): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($companyId, $userId, $rows, &$created, &$updated): void {
            $grouped = $rows->groupBy('order_reference');

            foreach ($grouped as $orderReference => $orderRows) {
                $first = $orderRows->first();
                $branchId = DB::table('branches')
                    ->where('company_id', $companyId)
                    ->where('code', $first['branch_code'])
                    ->value('id');
                $customer = $this->findOrCreateCustomer->execute($companyId, $userId, [
                    'name' => $first['customer_name'],
                    'email' => $first['customer_email'] ?: null,
                    'phone' => $first['customer_phone'] ?: null,
                ]);
                $lines = $orderRows->map(function (array $row) use ($companyId): array {
                    $variant = ProductVariant::query()
                        ->forCompany($companyId)
                        ->where('sku', $row['sku'])
                        ->firstOrFail();

                    return [
                        'product_variant_id' => $variant->id,
                        'description' => $variant->name,
                        'quantity' => $row['quantity'],
                        'unit_price' => $row['unit_price'] ?: null,
                        'discount' => $row['discount'] ?: 0,
                    ];
                })->values()->all();
                $orderDate = $first['order_date'] ?: now()->toDateString();
                $calculations = $this->checkoutService->calculate($companyId, $lines, $orderDate);
                $sale = Sale::query()
                    ->forCompany($companyId)
                    ->where('source', 'import')
                    ->where('source_channel', 'spreadsheet')
                    ->where('external_reference', $orderReference)
                    ->first();
                $exists = $sale !== null;

                if (! $sale) {
                    $sale = new Sale([
                        'company_id' => $companyId,
                        'sale_number' => 'IMP-'.date('Ymd').'-'.strtoupper(Str::random(6)),
                        'source' => 'import',
                        'source_channel' => 'spreadsheet',
                        'external_reference' => $orderReference,
                        'created_by' => $userId,
                    ]);
                }

                $sale->fill([
                    'branch_id' => $branchId,
                    'register_id' => null,
                    'cashier_shift_id' => null,
                    'type' => 'catering',
                    'partner_id' => $customer['id'],
                    'customer_name' => $first['customer_name'],
                    'status' => 'confirmed',
                    'order_date' => $orderDate,
                    'fulfilment_date' => $first['fulfilment_date'],
                    'delivery_address' => $first['delivery_address'] ?: null,
                    'currency_id' => 1,
                    'exchange_rate' => 1,
                    'subtotal' => $calculations['subtotal'],
                    'discount_total' => $calculations['discount_total'],
                    'tax_total' => $calculations['tax_total'],
                    'total' => $calculations['total'],
                    'amount_paid' => '0.0000',
                    'notes' => $first['notes'] ?: null,
                    'updated_by' => $userId,
                ]);
                $sale->save();
                $sale->lines()->delete();

                foreach ($calculations['lines'] as $line) {
                    SaleLine::query()->create(array_merge($line, ['sale_id' => $sale->id]));
                }

                $exists ? $updated++ : $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }
}

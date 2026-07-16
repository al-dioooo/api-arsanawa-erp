<?php

namespace App\Modules\Platform\Services;

use App\Modules\Inventory\Actions\FindVariantBySku;
use App\Modules\Partners\Actions\FindOrCreateCustomer;
use App\Modules\Pos\Actions\FindRegisterIdByCode;
use App\Modules\Pos\Actions\ImportCateringSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PosCateringImportProcessor
{
    public function __construct(
        private readonly FindOrCreateCustomer $findOrCreateCustomer,
        private readonly FindVariantBySku $variantBySku,
        private readonly FindRegisterIdByCode $registerIdByCode,
        private readonly ImportCateringSale $importCateringSale,
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

        if (($row['sku'] ?? '') !== '' && $this->variantBySku->execute($companyId, $row['sku']) === null) {
            $errors['sku'][] = __('SKU was not found.');
        }

        if (($row['quantity'] ?? 0) <= 0) {
            $errors['quantity'][] = __('Quantity must be greater than zero.');
        }

        if (($row['payment_method'] ?? '') !== '' && ! in_array($row['payment_method'], ['cash', 'card', 'qris', 'transfer'], true)) {
            $errors['payment_method'][] = __('Payment method is not supported.');
        }

        if (($row['payment_method'] ?? '') !== '') {
            if (($row['import_register_code'] ?? '') === '') {
                $errors['import_register_code'][] = __('Import register code is required for paid form orders.');
            } elseif ($this->registerIdByCode->execute($companyId, $row['import_register_code']) === null) {
                $errors['import_register_code'][] = __('Import register was not found.');
            }
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
                    $variant = $this->variantBySku->execute($companyId, $row['sku'], mustExist: true);

                    return [
                        'product_variant_id' => $variant->id,
                        'description' => $variant->name,
                        'quantity' => $row['quantity'],
                        'unit_price' => $row['unit_price'] ?: null,
                        'discount' => $row['discount'] ?: 0,
                    ];
                })->values()->all();
                $paymentMethod = ($first['payment_method'] ?? '') ?: null;
                $registerId = $paymentMethod
                    ? $this->registerIdByCode->execute($companyId, $first['import_register_code'] ?? '')
                    : null;

                $wasCreated = $this->importCateringSale->execute($companyId, $userId, [
                    'external_reference' => $orderReference,
                    'source_channel' => ($first['source_channel'] ?? '') ?: 'spreadsheet',
                    'branch_id' => $branchId,
                    'register_id' => $registerId,
                    'partner_id' => $customer['id'],
                    'customer_name' => $first['customer_name'],
                    'order_date' => $first['order_date'] ?: now()->toDateString(),
                    'fulfilment_date' => $first['fulfilment_date'],
                    'fulfilment_time_window' => ($first['fulfilment_time_window'] ?? '') ?: null,
                    'delivery_address' => $first['delivery_address'] ?: null,
                    'notes' => $first['notes'] ?: null,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => ($first['payment_reference'] ?? '') ?: null,
                    'lines' => $lines,
                ]);

                $wasCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }
}

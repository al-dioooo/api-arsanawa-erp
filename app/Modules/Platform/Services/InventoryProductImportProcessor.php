<?php

namespace App\Modules\Platform\Services;

use App\Modules\Inventory\Actions\CheckCategoryPathHasChildren;
use App\Modules\Inventory\Actions\ImportProductRow;
use App\Modules\Platform\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryProductImportProcessor
{
    public function __construct(
        private readonly CheckCategoryPathHasChildren $categoryPathHasChildren,
        private readonly ImportProductRow $importProductRow,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, list<string>>
     */
    public function validateRow(int $companyId, array $row): array
    {
        $errors = [];

        foreach (['product_name', 'base_uom_code', 'sku'] as $field) {
            if (($row[$field] ?? '') === '') {
                $errors[$field][] = __('This field is required.');
            }
        }

        if (($row['branch_code'] ?? '') !== '' && ! DB::table('branches')->where('company_id', $companyId)->where('code', $row['branch_code'])->exists()) {
            $errors['branch_code'][] = __('Branch code was not found.');
        }

        if (($row['currency_code'] ?? '') !== '' && ! Currency::query()->where('code', $row['currency_code'])->exists()) {
            $errors['currency_code'][] = __('Currency code was not found.');
        }

        if (($row['category_path'] ?? '') !== '' && $this->categoryPathHasChildren->execute($companyId, (string) $row['category_path'])) {
            $errors['category_path'][] = __('Products can only be assigned to the lowest category level.');
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
            foreach ($rows as $row) {
                $wasCreated = $this->importProductRow->execute(
                    $companyId,
                    $userId,
                    $row,
                    $this->priceCurrencyId($row),
                );

                $wasCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * The currency a priced row's price list should carry.
     *
     * Resolved here because Platform owns currencies; Inventory is handed the id.
     *
     * @param  array<string, mixed>  $row
     */
    private function priceCurrencyId(array $row): ?int
    {
        if (($row['price'] ?? 0) <= 0 || ! $row['currency_code']) {
            return null;
        }

        return Currency::query()->where('code', $row['currency_code'])->value('id');
    }
}

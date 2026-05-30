<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\ProductUnitStockResolver;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Validation\ValidationException;

class RecordStockTransfer
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ProductUnitStockResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): StockTransfer
    {
        $items = array_map(function (array $item) use ($companyId, $user): array {
            $stockItem = $this->resolver->resolve($companyId, $item, $user->id);

            return array_merge($item, [
                'product_variant_id' => $stockItem['product_variant_id'],
                'product_unit_id' => $stockItem['product_unit_id'] ?? null,
            ]);
        }, $data['items']);

        return $this->stockService->recordTransfer([
            'company_id' => $companyId,
            'from_branch_id' => $data['from_branch_id'],
            'to_branch_id' => $data['to_branch_id'],
            'items' => $items,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }
}

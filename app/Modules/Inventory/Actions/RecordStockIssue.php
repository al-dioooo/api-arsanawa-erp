<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Services\ProductUnitStockResolver;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Validation\ValidationException;

class RecordStockIssue
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
    public function execute(int $companyId, User $user, array $data): void
    {
        $stockItem = $this->resolver->resolve($companyId, $data, $user->id);

        $this->stockService->recordIssue([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'],
            'product_variant_id' => $stockItem['product_variant_id'],
            'product_unit_id' => $stockItem['product_unit_id'] ?? null,
            'quantity' => $data['quantity'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }
}

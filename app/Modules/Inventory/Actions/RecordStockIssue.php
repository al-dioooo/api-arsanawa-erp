<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Validation\ValidationException;

class RecordStockIssue
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): void
    {
        $this->stockService->recordIssue([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'],
            'product_variant_id' => $data['product_variant_id'],
            'quantity' => $data['quantity'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }
}

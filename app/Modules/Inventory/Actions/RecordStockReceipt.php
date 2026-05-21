<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Services\StockService;

class RecordStockReceipt
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $companyId, User $user, array $data): StockLot
    {
        return $this->stockService->recordReceipt([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'],
            'product_variant_id' => $data['product_variant_id'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'lot_number' => $data['lot_number'] ?? null,
            'received_at' => $data['received_at'] ?? now()->toDateString(),
            'expiry_date' => $data['expiry_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }
}

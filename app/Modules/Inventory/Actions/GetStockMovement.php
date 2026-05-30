<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockMovement;

class GetStockMovement
{
    public function execute(int $companyId, int $movementId): StockMovement
    {
        return StockMovement::query()
            ->where('company_id', $companyId)
            ->with(['productUnit.product', 'productUnit.variants.group', 'lot.productUnit'])
            ->findOrFail($movementId);
    }
}

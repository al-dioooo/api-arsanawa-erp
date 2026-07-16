<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\IssuedStock;
use Illuminate\Support\Collection;

class ListIssuedStock
{
    /**
     * Read contract for other modules: the stock issued against a document.
     *
     * @param  string  $referenceType  Morph class of the owning document.
     * @return Collection<int, IssuedStock>
     */
    public function execute(string $referenceType, int $referenceId): Collection
    {
        return StockMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('type', 'issue')
            ->get()
            ->map(fn (StockMovement $movement): IssuedStock => new IssuedStock(
                branchId: $movement->branch_id,
                productVariantId: $movement->product_variant_id,
                quantity: (string) $movement->quantity,
                unitCost: (string) $movement->unit_cost,
            ));
    }
}

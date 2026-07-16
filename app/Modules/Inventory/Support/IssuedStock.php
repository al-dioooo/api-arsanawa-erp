<?php

namespace App\Modules\Inventory\Support;

/**
 * Immutable, cross-module view of one stock issue recorded against a document.
 *
 * Lets other modules reverse or cost their own documents without reading the
 * StockMovement model.
 */
final readonly class IssuedStock
{
    /**
     * @param  string  $quantity  Signed as stored — issues are negative.
     */
    public function __construct(
        public int $branchId,
        public int $productVariantId,
        public string $quantity,
        public string $unitCost,
    ) {}
}

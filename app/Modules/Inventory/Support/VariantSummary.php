<?php

namespace App\Modules\Inventory\Support;

/**
 * Immutable, cross-module view of a product variant.
 *
 * Carries the handful of facts other modules need to price and describe a cart
 * line, so they never reach for the ProductVariant model.
 */
final readonly class VariantSummary
{
    public function __construct(
        public int $id,
        public int $productId,
        public ?int $categoryId,
        public ?string $name,
        public ?string $sku,
    ) {}
}

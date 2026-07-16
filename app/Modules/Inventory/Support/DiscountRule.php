<?php

namespace App\Modules\Inventory\Support;

/**
 * Immutable, cross-module view of a discount and its attached rules.
 *
 * Flattens the targets/dependencies/giveaways relations so consumers can
 * evaluate a discount without touching Inventory's models.
 */
final readonly class DiscountRule
{
    /**
     * @param  list<array{target_type: string, target_id: int}>  $targets
     * @param  list<array{product_variant_id: int, required_quantity: int}>  $dependencies
     * @param  list<array{product_variant_id: int, giveaway_quantity: int}>  $giveaways
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $calculationType,
        public string $value,
        public ?int $minQuantity,
        public array $targets,
        public array $dependencies,
        public array $giveaways,
    ) {}
}

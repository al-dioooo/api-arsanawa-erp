<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Discount;
use App\Modules\Inventory\Models\DiscountDependency;
use App\Modules\Inventory\Models\DiscountGiveaway;
use App\Modules\Inventory\Models\DiscountTarget;
use App\Modules\Inventory\Support\DiscountRule;
use Illuminate\Support\Collection;

class ListActiveDiscounts
{
    /**
     * Read contract for other modules: the discounts in force for a branch on a date.
     *
     * Owns the in-force rules (active, within the effective window, and either
     * company-wide or scoped to the branch) so callers never restate them.
     *
     * @param  string  $on  Date the discounts must be effective on (Y-m-d).
     * @return Collection<int, DiscountRule> Ordered by discount id.
     */
    public function execute(int $companyId, int $branchId, string $on): Collection
    {
        return Discount::query()
            ->forCompany($companyId)
            ->with(['targets', 'dependencies', 'giveaways'])
            ->where('is_active', true)
            ->where('effective_from', '<=', $on)
            ->where(function ($query) use ($on): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $on);
            })
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Discount $discount): DiscountRule => new DiscountRule(
                id: $discount->id,
                name: $discount->name,
                calculationType: $discount->calculation_type,
                value: (string) $discount->value,
                minQuantity: $discount->min_quantity,
                targets: $discount->targets
                    ->map(fn (DiscountTarget $target): array => [
                        'target_type' => $target->target_type,
                        'target_id' => $target->target_id,
                    ])
                    ->all(),
                dependencies: $discount->dependencies
                    ->map(fn (DiscountDependency $dependency): array => [
                        'product_variant_id' => $dependency->product_variant_id,
                        'required_quantity' => $dependency->required_quantity,
                    ])
                    ->all(),
                giveaways: $discount->giveaways
                    ->map(fn (DiscountGiveaway $giveaway): array => [
                        'product_variant_id' => $giveaway->product_variant_id,
                        'giveaway_quantity' => $giveaway->giveaway_quantity,
                    ])
                    ->all(),
            ));
    }
}

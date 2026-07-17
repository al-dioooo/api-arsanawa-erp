<?php

namespace App\Modules\Pos\Services;

use App\Modules\Inventory\Actions\ListActiveDiscounts;
use App\Modules\Inventory\Actions\ListVariantSummaries;
use App\Modules\Inventory\Support\DiscountRule;
use App\Modules\Inventory\Support\VariantSummary;
use Illuminate\Support\Collection;

class PromotionEvaluator
{
    public function __construct(
        private readonly ListActiveDiscounts $activeDiscounts,
        private readonly ListVariantSummaries $variantSummaries,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{promotions: array<int, array<string, mixed>>, giveaways: array<int, array<string, mixed>>}
     */
    public function evaluate(int $companyId, int $branchId, array $lines, ?string $on = null): array
    {
        $on ??= now()->toDateString();
        $promotions = [];
        $giveaways = [];

        $discounts = $this->activeDiscounts->execute($companyId, $branchId, $on);

        // Targeted discounts all match against the same cart, so load the
        // cart's variants once instead of once per discount.
        $cartVariants = $discounts->contains(fn (DiscountRule $discount): bool => $discount->targets !== [])
            ? $this->variantSummaries->execute(array_column($lines, 'product_variant_id'))
            : new Collection;

        foreach ($discounts as $discount) {
            if (! $this->dependenciesMet($discount, $lines)) {
                continue;
            }

            $eligibleLines = $this->eligibleLines($discount, $lines, $cartVariants);
            $eligibleQuantity = array_reduce(
                $eligibleLines,
                static fn (float $carry, array $line): float => $carry + (float) $line['quantity'],
                0.0,
            );

            if ($discount->minQuantity !== null && $eligibleQuantity < $discount->minQuantity) {
                continue;
            }

            $baseAmount = $this->baseAmount($eligibleLines);
            $amount = $this->discountAmount($discount, $baseAmount);

            if (bccomp($amount, '0.0000', 4) > 0 || $discount->giveaways !== []) {
                $promotions[] = [
                    'promotion_type' => 'discount',
                    'promotion_id' => $discount->id,
                    'description' => $discount->name,
                    'amount' => $amount,
                ];
            }

            foreach ($discount->giveaways as $giveaway) {
                $giveaways[] = [
                    'product_variant_id' => $giveaway['product_variant_id'],
                    'quantity' => $giveaway['giveaway_quantity'],
                    'unit_price' => 0,
                    'discount' => 0,
                    'is_giveaway' => true,
                ];
            }
        }

        return ['promotions' => $promotions, 'giveaways' => $giveaways];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function dependenciesMet(DiscountRule $discount, array $lines): bool
    {
        foreach ($discount->dependencies as $dependency) {
            $quantity = array_reduce($lines, function (float $carry, array $line) use ($dependency): float {
                if ((int) $line['product_variant_id'] !== $dependency['product_variant_id']) {
                    return $carry;
                }

                return $carry + (float) $line['quantity'];
            }, 0.0);

            if ($quantity < $dependency['required_quantity']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<int, VariantSummary>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function eligibleLines(DiscountRule $discount, array $lines, Collection $variants): array
    {
        if ($discount->targets === []) {
            return array_values(array_filter($lines, static fn (array $line): bool => ! ($line['is_giveaway'] ?? false)));
        }

        return array_values(array_filter($lines, function (array $line) use ($discount, $variants): bool {
            if ($line['is_giveaway'] ?? false) {
                return false;
            }

            $variant = $variants->get($line['product_variant_id']);

            foreach ($discount->targets as $target) {
                if ($target['target_type'] === 'variant' && (int) $line['product_variant_id'] === $target['target_id']) {
                    return true;
                }

                if ($target['target_type'] === 'product' && $variant?->productId === $target['target_id']) {
                    return true;
                }

                if ($target['target_type'] === 'category' && $variant?->categoryId === $target['target_id']) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function baseAmount(array $lines): string
    {
        return array_reduce($lines, static function (string $carry, array $line): string {
            return bcadd($carry, (string) $line['line_subtotal'], 4);
        }, '0.0000');
    }

    private function discountAmount(DiscountRule $discount, string $baseAmount): string
    {
        if ($discount->calculationType === 'percentage') {
            $fraction = bcdiv($discount->value, '100', 6);

            return bcmul($baseAmount, $fraction, 4);
        }

        if (bccomp($discount->value, $baseAmount, 4) > 0) {
            return $baseAmount;
        }

        return number_format((float) $discount->value, 4, '.', '');
    }
}

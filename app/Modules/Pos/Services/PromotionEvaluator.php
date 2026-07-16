<?php

namespace App\Modules\Pos\Services;

use App\Modules\Inventory\Models\Discount;
use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

class PromotionEvaluator
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{promotions: array<int, array<string, mixed>>, giveaways: array<int, array<string, mixed>>}
     */
    public function evaluate(int $companyId, int $branchId, array $lines, ?string $on = null): array
    {
        $on ??= now()->toDateString();
        $promotions = [];
        $giveaways = [];

        $discounts = Discount::query()
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
            ->get();

        // Targeted discounts all match against the same cart, so load the
        // cart's variants once instead of once per discount.
        $cartVariants = $discounts->contains(fn (Discount $discount): bool => $discount->targets->isNotEmpty())
            ? ProductVariant::query()
                ->whereIn('id', array_column($lines, 'product_variant_id'))
                ->with('product')
                ->get()
                ->keyBy('id')
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

            if ($discount->min_quantity !== null && $eligibleQuantity < $discount->min_quantity) {
                continue;
            }

            $baseAmount = $this->baseAmount($eligibleLines);
            $amount = $this->discountAmount($discount, $baseAmount);

            if (bccomp($amount, '0.0000', 4) > 0 || $discount->giveaways->isNotEmpty()) {
                $promotions[] = [
                    'promotion_type' => 'discount',
                    'promotion_id' => $discount->id,
                    'description' => $discount->name,
                    'amount' => $amount,
                ];
            }

            foreach ($discount->giveaways as $giveaway) {
                $giveaways[] = [
                    'product_variant_id' => $giveaway->product_variant_id,
                    'quantity' => $giveaway->giveaway_quantity,
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
    private function dependenciesMet(Discount $discount, array $lines): bool
    {
        foreach ($discount->dependencies as $dependency) {
            $quantity = array_reduce($lines, function (float $carry, array $line) use ($dependency): float {
                if ((int) $line['product_variant_id'] !== $dependency->product_variant_id) {
                    return $carry;
                }

                return $carry + (float) $line['quantity'];
            }, 0.0);

            if ($quantity < $dependency->required_quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function eligibleLines(Discount $discount, array $lines, Collection $variants): array
    {
        if ($discount->targets->isEmpty()) {
            return array_values(array_filter($lines, static fn (array $line): bool => ! ($line['is_giveaway'] ?? false)));
        }

        return array_values(array_filter($lines, function (array $line) use ($discount, $variants): bool {
            if ($line['is_giveaway'] ?? false) {
                return false;
            }

            $variant = $variants->get($line['product_variant_id']);

            foreach ($discount->targets as $target) {
                if ($target->target_type === 'variant' && (int) $line['product_variant_id'] === $target->target_id) {
                    return true;
                }

                if ($target->target_type === 'product' && $variant?->product_id === $target->target_id) {
                    return true;
                }

                if ($target->target_type === 'category' && $variant?->product?->category_id === $target->target_id) {
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

    private function discountAmount(Discount $discount, string $baseAmount): string
    {
        if ($discount->calculation_type === 'percentage') {
            $fraction = bcdiv((string) $discount->value, '100', 6);

            return bcmul($baseAmount, $fraction, 4);
        }

        if (bccomp((string) $discount->value, $baseAmount, 4) > 0) {
            return $baseAmount;
        }

        return number_format((float) $discount->value, 4, '.', '');
    }
}

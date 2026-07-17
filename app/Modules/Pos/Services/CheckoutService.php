<?php

namespace App\Modules\Pos\Services;

use App\Modules\Finance\Services\TaxCalculator;
use App\Modules\Inventory\Actions\ListVariantSummaries;
use App\Modules\Inventory\Actions\ResolvePrice;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly ResolvePrice $resolvePrice,
        private readonly TaxCalculator $taxCalculator,
        private readonly ListVariantSummaries $variantSummaries,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{subtotal: string, discount_total: string, tax_total: string, total: string, lines: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function calculate(int $companyId, array $lines, ?string $on = null): array
    {
        // Batch-load every referenced variant once instead of a query per line.
        $variants = $this->variantSummaries->execute(
            array_column($lines, 'product_variant_id'),
            $companyId,
        );

        $preparedLines = [];

        foreach ($lines as $line) {
            $variant = $variants->get($line['product_variant_id']);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'lines' => [__('One or more product variants do not belong to this company.')],
                ]);
            }

            $isGiveaway = (bool) ($line['is_giveaway'] ?? false);
            $unitPrice = $line['unit_price'] ?? null;

            if ($unitPrice === null && ! $isGiveaway) {
                $resolved = $this->resolvePrice->execute($variant->id, ['on' => $on ?? now()->toDateString()]);
                $unitPrice = $resolved['price'];
            }

            if ($unitPrice === null) {
                throw ValidationException::withMessages([
                    'lines' => [__('A price could not be resolved for variant :id.', ['id' => $variant->id])],
                ]);
            }

            $preparedLines[] = [
                'product_variant_id' => $variant->id,
                'description' => $line['description'] ?? ($variant->name ?: $variant->sku),
                'quantity' => $line['quantity'],
                'unit_price' => $isGiveaway ? '0.0000' : $unitPrice,
                'discount' => $line['discount'] ?? 0,
                'tax_rate_id' => $line['tax_rate_id'] ?? null,
                'revenue_account_id' => $line['revenue_account_id'] ?? null,
                'is_giveaway' => $isGiveaway,
            ];
        }

        $calculations = $this->taxCalculator->calculate($preparedLines);

        foreach ($preparedLines as $index => $line) {
            $preparedLines[$index] = array_merge($line, $calculations['lines'][$index]);
        }

        return [
            'subtotal' => $calculations['subtotal'],
            'discount_total' => $calculations['discount_total'],
            'tax_total' => $calculations['tax_total'],
            'total' => $calculations['total'],
            'lines' => $preparedLines,
        ];
    }
}

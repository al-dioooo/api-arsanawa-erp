<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\TaxRate;

class TaxCalculator
{
    /**
     * Calculate tax and totals for a set of document lines.
     *
     * @param  array<int, array{quantity: string|float|int, unit_price: string|float|int, discount?: string|float|int|null, tax_rate_id?: int|null}>  $lines
     * @return array{
     *     subtotal: string,
     *     discount_total: string,
     *     tax_total: string,
     *     withholding_total: string,
     *     total: string,
     *     lines: array<int, array{
     *         line_subtotal: string,
     *         tax_amount: string,
     *         withholding_amount: string,
     *         line_total: string
     *     }>
     * }
     */
    public function calculate(array $lines): array
    {
        $subtotal = '0.0000';
        $discountTotal = '0.0000';
        $taxTotal = '0.0000';
        $withholdingTotal = '0.0000';
        $total = '0.0000';
        $calculatedLines = [];

        // Cache loaded tax rates to avoid multiple DB hits for the same tax rate
        $taxRateCache = [];

        foreach ($lines as $line) {
            $qty = number_format((float) ($line['quantity'] ?? 0), 4, '.', '');
            $price = number_format((float) ($line['unit_price'] ?? 0), 4, '.', '');
            $discount = number_format((float) ($line['discount'] ?? 0), 4, '.', '');

            $gross = bcmul($qty, $price, 4);
            $lineSubtotal = bcsub($gross, $discount, 4);

            $taxAmount = '0.0000';
            $withholdingAmount = '0.0000';
            $taxRateId = $line['tax_rate_id'] ?? null;

            if ($taxRateId) {
                if (! isset($taxRateCache[$taxRateId])) {
                    $taxRateCache[$taxRateId] = TaxRate::find($taxRateId);
                }

                $taxRate = $taxRateCache[$taxRateId];
                if ($taxRate) {
                    $rateFraction = bcdiv((string) $taxRate->rate, '100', 6);
                    if ($taxRate->type === 'withholding') {
                        $withholdingAmount = bcmul($lineSubtotal, $rateFraction, 4);
                    } else { // vat
                        $taxAmount = bcmul($lineSubtotal, $rateFraction, 4);
                    }
                }
            }

            // line_total = line_subtotal + tax_amount - withholding_amount
            $lineTotal = bcadd($lineSubtotal, $taxAmount, 4);
            $lineTotal = bcsub($lineTotal, $withholdingAmount, 4);

            $calculatedLines[] = [
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $taxAmount,
                'withholding_amount' => $withholdingAmount,
                'line_total' => $lineTotal,
            ];

            $subtotal = bcadd($subtotal, $lineSubtotal, 4);
            $discountTotal = bcadd($discountTotal, $discount, 4);
            $taxTotal = bcadd($taxTotal, $taxAmount, 4);
            $withholdingTotal = bcadd($withholdingTotal, $withholdingAmount, 4);
            $total = bcadd($total, $lineTotal, 4);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'withholding_total' => $withholdingTotal,
            'total' => $total,
            'lines' => $calculatedLines,
        ];
    }
}

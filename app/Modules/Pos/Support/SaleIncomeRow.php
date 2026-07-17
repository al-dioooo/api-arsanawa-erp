<?php

namespace App\Modules\Pos\Support;

/**
 * Immutable, cross-module view of one completed sale as recognised income.
 *
 * Carries only the facts a financial report needs, so reporting modules never
 * reach for the Sale model.
 */
final readonly class SaleIncomeRow
{
    /**
     * @param  string  $date  Recognition date (Y-m-d), empty when undated.
     * @param  string  $reference  The sale number.
     * @param  string  $party  Customer name, falling back to the linked partner.
     */
    public function __construct(
        public string $date,
        public string $reference,
        public string $party,
        public float $amount,
    ) {}
}

<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Services\StockService;

class ListStockLevels
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{on_hand: string}
     */
    public function execute(array $params): array
    {
        $onHand = $this->stockService->onHand(
            (int) $params['product_variant_id'],
            (int) $params['branch_id'],
        );

        return ['on_hand' => number_format($onHand, 4, '.', '')];
    }
}

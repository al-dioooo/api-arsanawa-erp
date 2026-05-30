<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Services\StockService;

class GetStockValuation
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{total_value: string}
     */
    public function execute(int $companyId, array $params = []): array
    {
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $productUnitId = isset($params['product_unit_id']) ? (int) $params['product_unit_id'] : null;
        $total = $this->stockService->valuation($companyId, $branchId, $productUnitId);

        return ['total_value' => number_format($total, 4, '.', '')];
    }
}

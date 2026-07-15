<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\GoodsReceipt;

class GetGoodsReceipt
{
    public function execute(int $companyId, int $goodsReceiptId): GoodsReceipt
    {
        return GoodsReceipt::query()
            ->where('company_id', $companyId)
            ->with([
                'partner',
                'lines.productUnit.product',
                'lines.productUnit.variants.group',
            ])
            ->findOrFail($goodsReceiptId);
    }
}

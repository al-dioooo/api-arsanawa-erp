<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'product_variant_id',
        'product_unit_id',
        'stock_lot_id',
        'stock_movement_id',
        'quantity',
        'unit_cost',
        'line_total',
        'lot_number',
        'expiry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'goods_receipt_id' => 'integer',
            'product_variant_id' => 'integer',
            'product_unit_id' => 'integer',
            'stock_lot_id' => 'integer',
            'stock_movement_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}

<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'product_variant_id',
        'stock_lot_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'occurred_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'product_variant_id' => 'integer',
            'stock_lot_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

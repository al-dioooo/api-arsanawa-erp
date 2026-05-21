<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLot extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'product_variant_id',
        'lot_number',
        'received_quantity',
        'remaining_quantity',
        'unit_cost',
        'received_at',
        'expiry_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'product_variant_id' => 'integer',
            'received_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'received_at' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

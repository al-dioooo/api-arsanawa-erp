<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'company_id',
        'sku',
        'barcode',
        'name',
        'attributes',
        'purchase_uom_id',
        'purchase_conversion_factor',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'company_id' => 'integer',
            'purchase_uom_id' => 'integer',
            'purchase_conversion_factor' => 'decimal:4',
            'is_active' => 'boolean',
            'attributes' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_uom_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

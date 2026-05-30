<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'company_id',
        'category_id',
        'brand_id',
        'base_uom_id',
        'name',
        'description',
        'track_stock',
        'attributes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'category_id' => 'integer',
            'brand_id' => 'integer',
            'base_uom_id' => 'integer',
            'track_stock' => 'boolean',
            'attributes' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

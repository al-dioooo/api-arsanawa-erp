<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Variant extends Model
{
    protected $fillable = [
        'company_id',
        'variant_group_id',
        'name',
        'code',
        'position',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'variant_group_id' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VariantGroup::class, 'variant_group_id');
    }

    public function productUnits(): BelongsToMany
    {
        return $this->belongsToMany(ProductUnit::class, 'product_unit_variant');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

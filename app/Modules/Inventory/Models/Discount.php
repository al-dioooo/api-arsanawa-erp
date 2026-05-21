<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'calculation_type',
        'value',
        'min_quantity',
        'starting_item_number',
        'multiply',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'value' => 'decimal:4',
            'min_quantity' => 'integer',
            'starting_item_number' => 'integer',
            'multiply' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DiscountTarget::class);
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(DiscountDependency::class);
    }

    public function giveaways(): HasMany
    {
        return $this->hasMany(DiscountGiveaway::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

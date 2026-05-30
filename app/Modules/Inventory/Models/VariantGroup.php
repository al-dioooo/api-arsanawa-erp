<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantGroup extends Model
{
    protected $fillable = [
        'company_id',
        'unit_of_measure_id',
        'name',
        'code',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'unit_of_measure_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

<?php

namespace App\Modules\Partners\Models;

use App\Modules\Organization\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'name',
        'code',
        'email',
        'phone',
        'tax_identifier',
        'national_id',
        'credit_limit',
        'transaction_limit',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'transaction_limit' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PartnerAddress::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}

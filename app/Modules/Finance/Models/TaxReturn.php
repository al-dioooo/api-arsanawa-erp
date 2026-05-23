<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxReturn extends Model
{
    protected $fillable = [
        'company_id',
        'tax_type',
        'period_start',
        'period_end',
        'status',
        'total_output',
        'total_input',
        'total_payable',
        'journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'total_output' => 'decimal:4',
            'total_input' => 'decimal:4',
            'total_payable' => 'decimal:4',
            'journal_entry_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TaxReturnLine::class, 'tax_return_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

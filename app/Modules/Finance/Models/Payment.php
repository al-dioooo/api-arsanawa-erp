<?php

namespace App\Modules\Finance\Models;

use App\Modules\Partners\Models\Partner;
use App\Modules\Platform\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'payment_number',
        'partner_id',
        'payment_type',
        'payment_date',
        'payment_method',
        'amount',
        'currency_id',
        'exchange_rate',
        'cash_account_id',
        'status',
        'notes',
        'journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'partner_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:4',
            'currency_id' => 'integer',
            'exchange_rate' => 'decimal:8',
            'cash_account_id' => 'integer',
            'journal_entry_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
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

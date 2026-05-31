<?php

namespace App\Modules\Pos\Models;

use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'register_id',
        'cashier_shift_id',
        'sale_number',
        'type',
        'partner_id',
        'customer_name',
        'status',
        'source',
        'source_channel',
        'external_reference',
        'external_api_key_id',
        'order_date',
        'fulfilment_date',
        'fulfilment_time_window',
        'delivery_address',
        'currency_id',
        'exchange_rate',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'amount_paid',
        'notes',
        'revenue_journal_entry_id',
        'cogs_journal_entry_id',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'register_id' => 'integer',
            'cashier_shift_id' => 'integer',
            'partner_id' => 'integer',
            'external_api_key_id' => 'integer',
            'order_date' => 'date',
            'fulfilment_date' => 'date',
            'currency_id' => 'integer',
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'revenue_journal_entry_id' => 'integer',
            'cogs_journal_entry_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(SalePromotion::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function revenueJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'revenue_journal_entry_id');
    }

    public function cogsJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cogs_journal_entry_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

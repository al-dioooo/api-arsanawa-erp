<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'receipt_number',
        'delivery_note_number',
        'partner_id',
        'receipt_date',
        'status',
        'total_cost',
        'notes',
        'bill_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'partner_id' => 'integer',
            'receipt_date' => 'date',
            'total_cost' => 'decimal:4',
            'bill_id' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'goods_receipt_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

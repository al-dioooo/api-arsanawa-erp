<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'company_id',
        'from_branch_id',
        'to_branch_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'from_branch_id' => 'integer',
            'to_branch_id' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Models\Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Models\Branch::class, 'to_branch_id');
    }
}

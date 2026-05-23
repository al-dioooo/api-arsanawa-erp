<?php

namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'reference',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_id' => 'integer',
            'amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

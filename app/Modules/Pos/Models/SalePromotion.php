<?php

namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePromotion extends Model
{
    protected $fillable = [
        'sale_id',
        'promotion_type',
        'promotion_id',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'sale_id' => 'integer',
            'promotion_id' => 'integer',
            'amount' => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

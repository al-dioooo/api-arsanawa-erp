<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountTarget extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'discount_id',
        'target_type',
        'target_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_id' => 'integer',
            'target_id' => 'integer',
        ];
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}

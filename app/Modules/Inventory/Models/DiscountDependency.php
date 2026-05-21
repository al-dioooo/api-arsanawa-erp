<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountDependency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'discount_id',
        'product_variant_id',
        'required_quantity',
    ];

    protected function casts(): array
    {
        return [
            'discount_id' => 'integer',
            'product_variant_id' => 'integer',
            'required_quantity' => 'integer',
        ];
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}

<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    protected $fillable = [
        'price_list_id',
        'product_variant_id',
        'price',
        'maximum_retail_price',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price_list_id' => 'integer',
            'product_variant_id' => 'integer',
            'price' => 'decimal:4',
            'maximum_retail_price' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

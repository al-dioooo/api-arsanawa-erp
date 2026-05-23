<?php

namespace App\Modules\Pos\Models;

use App\Modules\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLine extends Model
{
    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'line_subtotal',
        'tax_amount',
        'line_total',
        'tax_rate_id',
        'revenue_account_id',
        'is_giveaway',
    ];

    protected function casts(): array
    {
        return [
            'sale_id' => 'integer',
            'product_variant_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'tax_rate_id' => 'integer',
            'revenue_account_id' => 'integer',
            'is_giveaway' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

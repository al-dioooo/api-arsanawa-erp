<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBranchAvailability extends Model
{
    protected $table = 'product_branch_availability';

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_variant_id',
        'is_available',
        'is_exclusive',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'product_variant_id' => 'integer',
            'is_available' => 'boolean',
            'is_exclusive' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

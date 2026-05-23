<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaxReturnLine extends Model
{
    protected $fillable = [
        'tax_return_id',
        'source_type',
        'source_id',
        'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'tax_return_id' => 'integer',
            'source_id' => 'integer',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}

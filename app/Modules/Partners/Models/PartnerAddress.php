<?php

namespace App\Modules\Partners\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAddress extends Model
{
    protected $fillable = [
        'partner_id',
        'type',
        'label',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}

<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductImage extends Model
{
    protected $fillable = [
        'company_id',
        'imageable_type',
        'imageable_id',
        'disk',
        'path',
        'url',
        'original_url',
        'alt_text',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'is_primary',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'imageable_id' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}

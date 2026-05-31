<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'raw',
        'normalized',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'import_batch_id' => 'integer',
            'row_number' => 'integer',
            'raw' => 'array',
            'normalized' => 'array',
            'errors' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}

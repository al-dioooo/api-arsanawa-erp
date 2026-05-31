<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'kind',
        'source',
        'source_path',
        'source_url',
        'original_name',
        'mime_type',
        'sheets',
        'selected_sheet',
        'status',
        'row_count',
        'error_count',
        'created_count',
        'updated_count',
        'failure_message',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'sheets' => 'array',
            'row_count' => 'integer',
            'error_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'committed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

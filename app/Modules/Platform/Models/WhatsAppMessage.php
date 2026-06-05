<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'company_id',
        'to',
        'body',
        'related_type',
        'related_id',
        'status',
        'provider',
        'provider_message_id',
        'error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'related_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

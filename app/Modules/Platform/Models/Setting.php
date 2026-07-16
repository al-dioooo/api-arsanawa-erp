<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Support\SecretSettings;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'module',
        'key',
        'value',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'value' => 'json',
        ];
    }

    /**
     * Whether this setting holds a credential and must never be echoed back.
     */
    public function isSecret(): bool
    {
        return SecretSettings::is($this->module, $this->key);
    }

    /**
     * Whether a value is stored, without reading the value itself.
     *
     * Deliberately works off the raw attribute: callers use this to report
     * "configured / not configured" for secrets, and must not have to touch the
     * plaintext to do so.
     */
    public function hasValue(): bool
    {
        $raw = $this->getRawOriginal('value');

        // A stored empty string is the raw two-character JSON '""', which is not
        // blank until it is decoded — so decode before deciding.
        return $raw !== null && filled(json_decode($raw, true));
    }
}

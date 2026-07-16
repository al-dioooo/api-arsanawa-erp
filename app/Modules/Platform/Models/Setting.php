<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Support\SecretSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class Setting extends Model
{
    /**
     * Marks a stored value as ciphertext. Versioned so the scheme can change
     * without a blocking data migration.
     */
    private const ENCRYPTED_PREFIX = 'enc:v1:';

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
            // 'value' is handled by the value() attribute below, which owns both
            // the JSON round-trip and encryption. Declaring a cast here as well
            // would be silently ignored (an attribute mutator takes precedence).
        ];
    }

    /**
     * The stored value: JSON-encoded for every setting, and additionally
     * encrypted at rest for registry-listed credentials.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $stored, array $attributes): mixed => $this->decryptIfSecret(
                $stored === null ? null : json_decode($stored, true),
                $attributes,
            ),
            set: fn (mixed $value, array $attributes): array => [
                // JSON_THROW_ON_ERROR preserves the old json cast's behaviour:
                // without it json_encode returns false and would silently write
                // `false` over the setting.
                'value' => json_encode($this->encryptIfSecret($value, $attributes), JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function encryptIfSecret(mixed $value, array $attributes): mixed
    {
        if (! SecretSettings::is($attributes['module'] ?? null, $attributes['key'] ?? null)) {
            return $value;
        }

        // Leave null/empty alone: those mean "unchanged" and "clear", and there
        // is nothing to protect in either case.
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return self::ENCRYPTED_PREFIX.Crypt::encryptString($value);
    }

    private function decryptIfSecret(mixed $decoded, array $attributes): mixed
    {
        // Gate on the registry before the prefix. Checking the prefix alone
        // would let any client write "enc:v1:garbage" to an arbitrary key (the
        // upsert endpoint accepts free-form module/key/value) and permanently
        // break every read of that module — including reads inside authorize().
        if (! SecretSettings::is($attributes['module'] ?? null, $attributes['key'] ?? null)) {
            return $decoded;
        }

        // Credentials written before this rolled out are stored bare; read them
        // straight back so the rollout needs no blocking data migration.
        if (! is_string($decoded) || ! str_starts_with($decoded, self::ENCRYPTED_PREFIX)) {
            return $decoded;
        }

        try {
            return Crypt::decryptString(substr($decoded, strlen(self::ENCRYPTED_PREFIX)));
        } catch (DecryptException $exception) {
            throw new RuntimeException(sprintf(
                'Unable to decrypt setting [%s.%s]; APP_KEY may have rotated. If this is an '
                .'intentional rotation, add the previous key to APP_PREVIOUS_KEYS.',
                $attributes['module'] ?? '?',
                $attributes['key'] ?? '?',
            ), previous: $exception);
        }
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

<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $source_channel
 * @property string $token_prefix
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ExternalApiKey extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'source_channel',
        'token_prefix',
        'token_hash',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array{apiKey: self, plainTextKey: string}
     */
    public static function issueFor(Company $company, User $user, array $data): array
    {
        $secret = Str::random(80);

        $apiKey = self::query()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'source_channel' => $data['source_channel'],
            'token_prefix' => substr($secret, 0, 12),
            'token_hash' => hash('sha256', $secret),
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            'apiKey' => $apiKey,
            'plainTextKey' => "{$apiKey->id}|{$secret}",
        ];
    }

    public function rotate(User $user): string
    {
        $secret = Str::random(80);

        $this->forceFill([
            'token_prefix' => substr($secret, 0, 12),
            'token_hash' => hash('sha256', $secret),
            'revoked_at' => null,
            'updated_by' => $user->id,
        ])->save();

        return "{$this->id}|{$secret}";
    }

    public static function findValidKey(string $plainTextKey): ?self
    {
        [$id, $secret] = array_pad(explode('|', $plainTextKey, 2), 2, null);

        if (! ctype_digit((string) $id) || ! is_string($secret)) {
            return null;
        }

        /** @var self|null $apiKey */
        $apiKey = self::query()
            ->with('company')
            ->whereKey((int) $id)
            ->first();

        if (! $apiKey || ! hash_equals($apiKey->token_hash, hash('sha256', $secret))) {
            return null;
        }

        if ($apiKey->revoked_at !== null) {
            return null;
        }

        if ($apiKey->expires_at !== null && $apiKey->expires_at->isPast()) {
            return null;
        }

        if ($apiKey->company?->status !== 'active') {
            return null;
        }

        return $apiKey;
    }

    public function touchLastUsedAt(): void
    {
        if ($this->last_used_at === null || $this->last_used_at->lt(now()->subMinutes(5))) {
            $this->forceFill(['last_used_at' => now()])->save();
        }
    }

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}

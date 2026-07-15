<?php

namespace App\Modules\Authentication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property array<int, string>|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class AuthAccessToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a new token for a user.
     *
     * Expiry is driven by `auth.access_token_lifetime_hours` (default 24).
     * Set ACCESS_TOKEN_LIFETIME_HOURS in .env to override.
     *
     * @return array{accessToken: self, plainTextToken: string}
     */
    public static function issueFor(User $user, string $name = 'api'): array
    {
        $plainTextToken = Str::random(80);

        $accessToken = self::query()->create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'expires_at' => now()->addHours((int) config('auth.access_token_lifetime_hours', 24)),
        ]);

        return [
            'accessToken' => $accessToken,
            'plainTextToken' => "{$accessToken->getKey()}|{$plainTextToken}",
        ];
    }

    /**
     * Look up a valid, unexpired token from a raw bearer string.
     * Returns null if the token is missing, tampered, or expired.
     */
    public static function findValidToken(string $bearerToken): ?self
    {
        [$id, $plainTextToken] = array_pad(explode('|', $bearerToken, 2), 2, null);

        if (! is_string($id) || ! is_string($plainTextToken)) {
            return null;
        }

        /** @var self|null $accessToken */
        $accessToken = self::query()
            ->with('user')
            ->whereKey($id)
            ->first();

        if (! $accessToken || ! hash_equals($accessToken->token_hash, hash('sha256', $plainTextToken))) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        // Suspended or pending accounts must not be able to use existing tokens.
        if (! $accessToken->user || ! $accessToken->user->isActive()) {
            return null;
        }

        return $accessToken;
    }

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

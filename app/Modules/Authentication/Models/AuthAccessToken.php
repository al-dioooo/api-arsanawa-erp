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
        ]);

        return [
            'accessToken' => $accessToken,
            'plainTextToken' => "{$accessToken->getKey()}|{$plainTextToken}",
        ];
    }

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

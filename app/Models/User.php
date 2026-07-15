<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Authentication\Models\AuthAccessToken;
use App\Modules\Identity\Models\UserStatus;
use App\Modules\Organization\Models\Membership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    private ?AuthAccessToken $currentAccessToken = null;

    /**
     * @return HasMany<AuthAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(AuthAccessToken::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasOne<UserStatus, $this>
     */
    public function status(): HasOne
    {
        return $this->hasOne(UserStatus::class);
    }

    /**
     * Whether the account may authenticate. Absent status rows are treated as
     * active (status is created on demand and defaults to active).
     */
    public function isActive(): bool
    {
        $status = $this->status?->status;

        return $status === null || $status === 'active';
    }

    public function currentAccessToken(): ?AuthAccessToken
    {
        return $this->currentAccessToken;
    }

    public function setCurrentAccessToken(AuthAccessToken $accessToken): void
    {
        $this->currentAccessToken = $accessToken;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_developer' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Authentication\Models\AuthAccessToken;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private ?AuthAccessToken $currentAccessToken = null;

    /**
     * @return HasMany<AuthAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(AuthAccessToken::class);
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
        ];
    }
}

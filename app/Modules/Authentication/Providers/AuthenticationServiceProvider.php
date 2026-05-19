<?php

namespace App\Modules\Authentication\Providers;

use App\Models\User;
use App\Modules\Authentication\Guards\AccessTokenGuard;
use App\Modules\Authentication\Models\AuthAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AuthenticationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Authentication module services.
     */
    public function boot(): void
    {
        $this->registerAccessTokenGuard();
        $this->registerPasswordResetUrl();
        $this->registerPasswordDefaults();
        $this->registerRateLimiters();
    }

    /**
     * Register the custom bearer-token guard that resolves users
     * from hashed access tokens stored in the database.
     */
    private function registerAccessTokenGuard(): void
    {
        $resolveUser = fn (Request $request): ?User => $this->resolveUserFromAccessToken($request);

        Auth::extend('access-token', function ($app, string $name, array $config) use ($resolveUser): AccessTokenGuard {
            $guard = new AccessTokenGuard(
                $resolveUser,
                $app['request'],
                Auth::createUserProvider($config['provider'] ?? null),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    private function resolveUserFromAccessToken(Request $request): ?User
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return null;
        }

        $accessToken = AuthAccessToken::findValidToken($bearerToken);

        if (! $accessToken) {
            return null;
        }

        if (! $accessToken->last_used_at || $accessToken->last_used_at->lt(now()->subMinutes(5))) {
            $accessToken->forceFill(['last_used_at' => now()])->save();
        }

        $accessToken->user->setCurrentAccessToken($accessToken);

        return $accessToken->user;
    }

    /**
     * Configure password reset links to point to the Next.js frontend.
     */
    private function registerPasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $frontendUrl = rtrim((string) config('services.frontend.url'), '/');

            return $frontendUrl.'/reset-password?'.http_build_query([
                'email' => $user->email,
                'token' => $token,
            ]);
        });
    }

    /**
     * Set default password validation rules based on environment.
     */
    private function registerPasswordDefaults(): void
    {
        Password::defaults(fn () => app()->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }

    /**
     * Register rate limiters for authentication-related routes.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->string('login'));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.$request->string('email'));
        });
    }
}

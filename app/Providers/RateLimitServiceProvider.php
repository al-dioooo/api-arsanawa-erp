<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiters guarding the authenticated API surface.
 *
 * These live next to the `throttleApi('api')` switch in bootstrap/app.php on
 * purpose: `throttle:api` is load-bearing for every API route, and a missing
 * limiter makes ThrottleRequests throw MissingRateLimiterException — i.e. a
 * 500 on every request. Keep the switch and its limiters together.
 *
 * The auth-related limiters (login, password-reset, external-api) stay in the
 * Authentication module, which owns those routes.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Ordinary business endpoints: an unforgeable per-user ceiling plus a
        // per-user+company ceiling. Returning both means the company key cannot
        // be used to mint fresh buckets (see companyKey()).
        RateLimiter::for('api', function (Request $request) {
            if (! config('rate-limiting.enabled')) {
                return Limit::none();
            }

            $user = $request->user();

            if ($user === null) {
                return $this->limit((int) config('rate-limiting.guest'), 'guest|'.$request->ip());
            }

            return [
                $this->limit((int) config('rate-limiting.per_user'), 'user|'.$user->getAuthIdentifier()),
                $this->limit(
                    (int) config('rate-limiting.per_company'),
                    'company|'.$user->getAuthIdentifier().'|'.$this->companyKey($request),
                ),
            ];
        });

        $this->scopedLimiter('heavy', 'rate-limiting.heavy');
        $this->scopedLimiter('dashboard', 'rate-limiting.dashboard');
        $this->scopedLimiter('refresh', 'rate-limiting.refresh');
    }

    /**
     * A per-user limiter for a specific class of expensive routes. Stacks on top
     * of `throttle:api` rather than replacing it.
     */
    private function scopedLimiter(string $name, string $configKey): void
    {
        RateLimiter::for($name, function (Request $request) use ($name, $configKey) {
            if (! config('rate-limiting.enabled')) {
                return Limit::none();
            }

            $user = $request->user();
            $key = $user !== null ? 'user|'.$user->getAuthIdentifier() : 'guest|'.$request->ip();

            return $this->limit((int) config($configKey), $name.'|'.$key);
        });
    }

    /**
     * The company scope is read from the raw header, because SetCurrentCompany
     * is not in the middleware priority map and therefore runs *after* the
     * throttle — `active_company_id` is not set yet. The header is
     * caller-controlled, so this key is only ever used alongside the
     * unforgeable per-user limit above.
     */
    private function companyKey(Request $request): string
    {
        return (string) ($request->header('X-Company-Id') ?: 'none');
    }

    private function limit(int $perMinute, string $key): Limit
    {
        return Limit::perMinute($perMinute)->by($key)->response(
            fn (Request $request, array $headers): Response => new JsonResponse(
                [
                    'message' => __('Too many requests. Please slow down and try again shortly.'),
                    'data' => null,
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                $headers,
            )
        );
    }
}

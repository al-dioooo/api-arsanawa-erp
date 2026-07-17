<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authenticated API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Ceilings for the named limiters guarding the "api" middleware group.
    | When disabled every limiter returns Limit::none(), which ThrottleRequests
    | short-circuits without touching the cache. The Pest suite disables it via
    | phpunit.xml; an individual test may opt back in with
    | config()->set('rate-limiting.enabled', true).
    |
    | These are coarse abuse ceilings, not fairness quotas — they exist so a
    | compromised token cannot hammer reports, exports and imports unbounded.
    |
    */

    'enabled' => env('RATE_LIMIT_ENABLED', true),

    // Hard per-user ceiling across every company. Keyed on the user id, so it
    // cannot be widened by varying request headers.
    'per_user' => (int) env('RATE_LIMIT_PER_USER', 300),

    // Per-user, per-company ceiling for ordinary business endpoints.
    'per_company' => (int) env('RATE_LIMIT_PER_COMPANY', 120),

    // Backstop for unauthenticated requests that reach the api group. Note that
    // auth:api sorts ahead of the throttle middleware, so this only applies to
    // routes without an auth middleware — it does not cover token spraying.
    'guest' => (int) env('RATE_LIMIT_GUEST', 60),

    // Reports, exports and spreadsheet imports: expensive, rarely bursty.
    'heavy' => (int) env('RATE_LIMIT_HEAVY', 20),

    // Module dashboards are landing pages; ordinary navigation can reach 20/min,
    // so they get their own, looser ceiling rather than sharing "heavy".
    'dashboard' => (int) env('RATE_LIMIT_DASHBOARD', 60),

    // Access-token refresh. Deliberately generous: the web client treats any
    // non-ok refresh response (including 429) as a hard logout.
    'refresh' => (int) env('RATE_LIMIT_REFRESH', 12),

];

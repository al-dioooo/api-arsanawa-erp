<?php

$normalizeCorsOrigins = static function (?string $origins): array {
    return array_values(array_unique(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) $origins),
    ))));
};

$allowedOrigins = $normalizeCorsOrigins(env('CORS_ALLOWED_ORIGINS'));

if ($allowedOrigins === []) {
    $allowedOrigins = $normalizeCorsOrigins(env('FRONTEND_URL', 'https://arsanawa-erp.vercel.app'));
}

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

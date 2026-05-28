<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

describe('OpenAPI contract', function () {
    it('documents every registered API v1 route for scanner imports', function () {
        $path = base_path('openapi.yaml');

        expect($path)->toBeFile();

        $contract = Yaml::parseFile($path);

        expect($contract)
            ->toHaveKey('openapi', '3.0.3')
            ->toHaveKey('paths')
            ->and($contract['servers'])
            ->toBe([
                [
                    'url' => 'https://api-arsanawa-erp.test',
                    'description' => 'Local Herd HTTPS',
                ],
            ])
            ->and($contract['components']['securitySchemes']['bearerAuth']['type'] ?? null)
            ->toBe('http')
            ->and($contract['components']['parameters']['CompanyIdHeader']['name'] ?? null)
            ->toBe('X-Company-Id');

        $documentedOperations = collect($contract['paths'])
            ->flatMap(fn (array $operations, string $path): array => collect($operations)
                ->keys()
                ->filter(fn (string $method): bool => in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true))
                ->map(fn (string $method): string => strtoupper($method).' '.$path)
                ->all())
            ->sort()
            ->values();

        $registeredOperations = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
            ->flatMap(fn ($route): array => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): string => $method.' /'.preg_replace('/\{([^}:]+)(:[^}]+)?}/', '{$1}', $route->uri()))
                ->all())
            ->sort()
            ->values();

        expect($documentedOperations->all())->toBe($registeredOperations->all());
    });
});

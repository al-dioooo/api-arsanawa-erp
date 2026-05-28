<?php

describe('security headers', function () {
    it('adds anti-clickjacking and content-sniffing protections to API responses', function () {
        $response = $this->getJson('/api/v1/auth/me');

        $response
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        expect($response->headers->get('Content-Security-Policy'))
            ->toBe("default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; object-src 'none'; script-src 'none'; style-src 'none'; img-src 'none'; font-src 'none'; connect-src 'none'; frame-src 'none'; media-src 'none'; manifest-src 'none'; worker-src 'none'");

        expect($response->headers->has('X-Powered-By'))->toBeFalse();
    });

    it('adds HSTS only to secure responses', function () {
        $plainResponse = $this->getJson('/api/v1/auth/me');

        expect($plainResponse->headers->has('Strict-Transport-Security'))->toBeFalse();

        $secureResponse = $this->getJson('https://localhost/api/v1/auth/me');

        $secureResponse
            ->assertUnauthorized()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    });

    it('serves the API root as JSON without the Laravel welcome page or external fonts', function () {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Arsanawa ERP API',
                'data' => null,
            ]);

        expect($response->headers->get('Content-Type'))->toContain('application/json')
            ->and($response->headers->has('Set-Cookie'))->toBeFalse()
            ->and($response->getContent())->not->toContain('<html')
            ->and($response->getContent())->not->toContain('fonts.bunny.net');
    });
});

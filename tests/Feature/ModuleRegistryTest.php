<?php

use App\Models\User;

describe('GET /api/v1/modules', function () {
    it('returns enabled and available module lists for an authenticated user', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $response = $this->withToken($token)
            ->getJson('/api/v1/modules')
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => ['enabled', 'available'],
            ]);

        expect($response->json('data.enabled'))->toBeArray();
        expect($response->json('data.available'))->toBeArray();
    });

    it('returns a cacheable response on repeated calls', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $first = $this->withToken($token)
            ->getJson('/api/v1/modules')
            ->assertSuccessful()
            ->json('data');

        $second = $this->withToken($token)
            ->getJson('/api/v1/modules')
            ->assertSuccessful()
            ->json('data');

        expect($first)->toBe($second);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/modules')
            ->assertUnauthorized();
    });
});

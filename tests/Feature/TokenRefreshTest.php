<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

describe('POST /api/v1/auth/refresh', function () {
    it('issues a new token and revokes the current one', function () {
        $user = User::factory()->create();

        $oldToken = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $response = $this->withToken($oldToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => ['token_type', 'access_token', 'expires_at', 'user'],
            ]);

        $newToken = $response->json('data.access_token');

        expect($newToken)->not->toBe($oldToken);

        // old token must be dead — forget cached guard state so the token is re-validated
        Auth::forgetGuards();
        $this->withToken($oldToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // new token must work
        $this->withToken($newToken)
            ->getJson('/api/v1/auth/me')
            ->assertSuccessful()
            ->assertJsonPath('data.id', $user->id);
    });

    it('gives the refreshed token a fresh expiry window from now', function () {
        $user = User::factory()->create();

        $oldToken = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        // travel close to expiry of the original token
        $this->travel(23)->hours();

        $newToken = $this->withToken($oldToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertSuccessful()
            ->json('data.access_token');

        // 2 more hours: original would be expired (25h total), new token is only 2h old
        $this->travel(2)->hours();

        $this->withToken($newToken)
            ->getJson('/api/v1/auth/me')
            ->assertSuccessful();
    });

    it('preserves the token name from the original token', function () {
        $user = User::factory()->create();

        $oldToken = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'iphone',
        ])->json('data.access_token');

        $this->withToken($oldToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertSuccessful();

        expect($user->fresh()->accessTokens()->latest()->first()->name)->toBe('iphone');
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized();
    });
});

<?php

use App\Models\User;

describe('token expiry', function () {
    it('rejects a token that has passed its expiry time', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->travel(25)->hours();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });

    it('accepts a token within its expiry window', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->travel(23)->hours();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertSuccessful()
            ->assertJsonPath('data.id', $user->id);
    });

    it('includes expires_at in the login response', function () {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => ['token_type', 'access_token', 'expires_at', 'user'],
            ])
            ->assertJsonPath('data.token_type', 'Bearer');
    });
});

<?php

use App\Models\User;
use Database\Seeders\CurrencySeeder;

describe('GET /api/v1/platform/currencies', function () {
    it('lists active currencies for an authenticated user', function (): void {
        $this->seed(CurrencySeeder::class);

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/platform/currencies')
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'currencies' => [
                        ['id', 'code', 'name', 'symbol', 'decimal_places', 'is_active'],
                    ],
                ],
            ])
            ->assertJsonFragment(['code' => 'IDR', 'decimal_places' => 2]);
    });

    it('requires authentication', function (): void {
        $this->getJson('/api/v1/platform/currencies')->assertUnauthorized();
    });
});

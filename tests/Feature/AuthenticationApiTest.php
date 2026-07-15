<?php

use App\Models\User;
use App\Modules\Identity\Models\UserStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

describe('account status enforcement', function () {
    it('rejects login for a suspended account even with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'correct-password',
        ]);
        UserStatus::create(['user_id' => $user->id, 'status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'suspended@example.com',
            'password' => 'correct-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('login');
    });

    it('rejects an existing token once the account is suspended', function () {
        $user = User::factory()->create(['password' => 'correct-password']);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'correct-password',
        ])->json('data.access_token');

        // Token works while active.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertSuccessful();

        UserStatus::create(['user_id' => $user->id, 'status' => 'suspended']);

        // The same token is now rejected.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });
});

describe('POST /api/v1/auth/login', function () {
    it('authenticates with an email and returns a bearer token', function () {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'alice@example.com',
            'password' => 'correct-password',
            'device_name' => 'next.js',
        ])
            ->assertSuccessful()
            ->assertJsonStructure(['message', 'data' => ['token_type', 'access_token', 'user']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);
    });

    it('authenticates with a username', function () {
        $user = User::factory()->create([
            'username' => 'alice',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'alice',
            'password' => 'correct-password',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.user.id', $user->id);
    });

    it('rejects invalid credentials', function () {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'alice@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    });

    it('rejects requests with missing required fields', function () {
        $this->postJson('/api/v1/auth/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'password']);
    });
});

describe('GET /api/v1/auth/me', function () {
    it('returns the authenticated user profile', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertSuccessful()
            ->assertJsonStructure(['message', 'data'])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });
});

describe('POST /api/v1/auth/logout', function () {
    it('revokes the current access token', function () {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertSuccessful()
            ->assertJsonStructure(['message', 'data']);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    });
});

describe('POST /api/v1/auth/forgot-password', function () {
    it('sends a password reset notification', function () {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'alice@example.com',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'alice@example.com',
        ])
            ->assertSuccessful()
            ->assertJsonStructure(['message', 'data']);

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('returns an error response for non-existent emails', function () {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'data']);
    });
});

describe('POST /api/v1/auth/reset-password', function () {
    it('resets the password and revokes existing access tokens', function () {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'old-password',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'alice@example.com',
            'password' => 'old-password',
        ]);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'alice@example.com',
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertSuccessful()
            ->assertJsonStructure(['message', 'data']);

        expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();

        $this->withToken($loginResponse->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });

    it('allows login with the new password after reset', function () {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'old-password',
        ]);

        $token = Password::createToken(User::where('email', 'alice@example.com')->first());

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'alice@example.com',
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSuccessful();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'alice@example.com',
            'password' => 'new-secure-password',
        ])->assertSuccessful();
    });
});

<?php

namespace App\Modules\Authentication\Actions;

use App\Models\User;
use App\Modules\Authentication\Models\AuthAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    /**
     * Verify credentials and issue an access token.
     *
     * @param  array{login: string, password: string, device_name?: string}  $credentials
     * @return array{user: User, accessToken: AuthAccessToken, plainTextToken: string}
     *
     * @throws ValidationException
     */
    public function execute(array $credentials): array
    {
        $login = $credentials['login'];

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => [__('auth.failed')],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'login' => [__('This account is not active. Please contact an administrator.')],
            ]);
        }

        $issued = AuthAccessToken::issueFor(
            user: $user,
            name: $credentials['device_name'] ?? 'api',
        );

        $user->setCurrentAccessToken($issued['accessToken']);

        return [
            'user' => $user,
            'accessToken' => $issued['accessToken'],
            'plainTextToken' => $issued['plainTextToken'],
        ];
    }
}

<?php

namespace App\Modules\Authentication\Actions;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetUserPassword
{
    /**
     * Reset the user's password and revoke all existing access tokens.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     * @return array{status: string, message: string, succeeded: bool}
     */
    public function execute(array $data): array
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->accessTokens()->delete();

                event(new PasswordReset($user));
            }
        );

        return [
            'status' => $status,
            'message' => __($status),
            'succeeded' => $status === Password::PasswordReset,
        ];
    }
}

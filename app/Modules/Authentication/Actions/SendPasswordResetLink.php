<?php

namespace App\Modules\Authentication\Actions;

use Illuminate\Support\Facades\Password;

class SendPasswordResetLink
{
    /**
     * Send a password reset link to the given email.
     *
     * @param  array{email: string}  $credentials
     * @return array{status: string, message: string, succeeded: bool}
     */
    public function execute(array $credentials): array
    {
        $status = Password::sendResetLink($credentials);

        return [
            'status' => $status,
            'message' => __($status),
            'succeeded' => $status === Password::ResetLinkSent,
        ];
    }
}

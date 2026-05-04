<?php

namespace App\Modules\Authentication\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RevokeAccessToken
{
    /**
     * Revoke the currently authenticated access token.
     */
    public function execute(User $user): void
    {
        $user->currentAccessToken()?->delete();

        Auth::guard('api')->forgetUser();
    }
}

<?php

namespace App\Modules\Authentication\Actions;

use App\Models\User;
use App\Modules\Authentication\Models\AuthAccessToken;

class RefreshAccessToken
{
    /**
     * Revoke the current access token and issue a fresh one.
     *
     * The new token inherits the device name from the revoked token
     * and receives a full new expiry window from the current time.
     *
     * @return array{user: User, accessToken: AuthAccessToken, plainTextToken: string}
     */
    public function execute(User $user): array
    {
        // Capture the name before revoking so the new token inherits it.
        $tokenName = $user->currentAccessToken()?->name ?? 'api';

        // Issue first — if this fails, the old token is still valid.
        $issued = AuthAccessToken::issueFor(user: $user, name: $tokenName);

        // Revoke only after the new token is safely persisted.
        $user->currentAccessToken()?->delete();

        $user->setCurrentAccessToken($issued['accessToken']);

        return [
            'user' => $user,
            'accessToken' => $issued['accessToken'],
            'plainTextToken' => $issued['plainTextToken'],
        ];
    }
}

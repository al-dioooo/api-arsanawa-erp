<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\ExternalApiKey;

class RevokeExternalApiKey
{
    public function execute(ExternalApiKey $apiKey, User $user): ExternalApiKey
    {
        $apiKey->forceFill([
            'revoked_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        return $apiKey->fresh();
    }
}

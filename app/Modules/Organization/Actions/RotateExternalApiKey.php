<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\ExternalApiKey;

class RotateExternalApiKey
{
    /**
     * @return array{apiKey: ExternalApiKey, plainTextKey: string}
     */
    public function execute(ExternalApiKey $apiKey, User $user): array
    {
        $plainTextKey = $apiKey->rotate($user);

        return [
            'apiKey' => $apiKey->fresh(),
            'plainTextKey' => $plainTextKey,
        ];
    }
}

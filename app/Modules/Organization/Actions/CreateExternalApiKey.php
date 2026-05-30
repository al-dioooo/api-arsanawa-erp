<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\ExternalApiKey;

class CreateExternalApiKey
{
    /**
     * @param  array{name: string, source_channel: string, expires_at?: string|null}  $data
     * @return array{apiKey: ExternalApiKey, plainTextKey: string}
     */
    public function execute(Company $company, User $user, array $data): array
    {
        return ExternalApiKey::issueFor($company, $user, $data);
    }
}

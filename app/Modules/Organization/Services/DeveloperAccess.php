<?php

namespace App\Modules\Organization\Services;

use App\Models\User;

class DeveloperAccess
{
    public function userIsDeveloper(?User $user): bool
    {
        return (bool) $user?->is_developer;
    }
}

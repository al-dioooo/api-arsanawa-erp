<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Membership;

class CheckActiveMembership
{
    /**
     * Read contract for other modules: whether a user is an active member of a company.
     */
    public function execute(int $companyId, int $userId): bool
    {
        return Membership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}

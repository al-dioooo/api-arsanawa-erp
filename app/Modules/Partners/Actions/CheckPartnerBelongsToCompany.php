<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;

class CheckPartnerBelongsToCompany
{
    /**
     * Read contract for other modules: whether a partner exists within a company.
     */
    public function execute(int $companyId, int $partnerId): bool
    {
        return Partner::query()
            ->where('company_id', $companyId)
            ->whereKey($partnerId)
            ->exists();
    }
}

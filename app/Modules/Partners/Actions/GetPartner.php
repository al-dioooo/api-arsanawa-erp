<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;

class GetPartner
{
    /**
     * @return array{partner: Partner}
     */
    public function execute(int $partnerId, int $companyId): array
    {
        $partner = Partner::where('id', $partnerId)
            ->where('company_id', $companyId)
            ->with('contacts', 'addresses')
            ->firstOrFail();

        return ['partner' => $partner];
    }
}

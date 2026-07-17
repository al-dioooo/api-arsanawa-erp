<?php

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Support\CompanyProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetCompanyProfile
{
    /**
     * Read contract for other modules: a company as a plain DTO.
     *
     * @param  bool  $mustExist  Throw instead of returning null when the company is missing.
     *
     * @throws ModelNotFoundException
     */
    public function execute(int $companyId, bool $mustExist = false): ?CompanyProfile
    {
        $company = $mustExist
            ? Company::query()->findOrFail($companyId)
            : Company::query()->find($companyId);

        if (! $company) {
            return null;
        }

        return new CompanyProfile(
            id: $company->id,
            name: $company->name,
            slug: $company->slug,
        );
    }
}

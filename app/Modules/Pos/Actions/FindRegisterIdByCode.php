<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Register;

class FindRegisterIdByCode
{
    /**
     * Read contract for other modules: resolve a company's register by code.
     *
     * @return int|null The register id, or null when the code is unknown.
     */
    public function execute(int $companyId, string $code): ?int
    {
        return Register::query()
            ->forCompany($companyId)
            ->where('code', $code)
            ->value('id');
    }
}

<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\TaxReturn;

class GetTaxReturn
{
    /**
     * Get a single tax return with its lines.
     */
    public function execute(int $companyId, int $id): TaxReturn
    {
        return TaxReturn::query()
            ->forCompany($companyId)
            ->with(['lines.source'])
            ->findOrFail($id);
    }
}

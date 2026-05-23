<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ApprovalMatrix;
use Illuminate\Database\Eloquent\Collection;

class ListApprovalMatrices
{
    /**
     * Execute the action.
     *
     * @return Collection<int, ApprovalMatrix>
     */
    public function execute(int $companyId): Collection
    {
        return ApprovalMatrix::query()
            ->forCompany($companyId)
            ->orderBy('document_type')
            ->orderBy('min_amount')
            ->orderBy('level')
            ->get();
    }
}

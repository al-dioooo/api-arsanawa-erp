<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ApprovalMatrix;

class DeleteApprovalMatrix
{
    public function execute(ApprovalMatrix $matrix): void
    {
        $matrix->delete();
    }
}

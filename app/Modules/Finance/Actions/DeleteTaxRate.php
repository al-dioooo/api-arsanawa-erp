<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\TaxRate;

class DeleteTaxRate
{
    public function execute(TaxRate $taxRate): void
    {
        $taxRate->delete();
    }
}

<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\TaxReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTaxReturn
{
    /**
     * Delete a draft tax return.
     *
     * @throws ValidationException
     */
    public function execute(TaxReturn $taxReturn): void
    {
        if ($taxReturn->status !== 'draft') {
            throw ValidationException::withMessages([
                'tax_return' => [__('Only draft tax returns can be deleted.')],
            ]);
        }

        DB::transaction(function () use ($taxReturn): void {
            $taxReturn->delete();
        });
    }
}

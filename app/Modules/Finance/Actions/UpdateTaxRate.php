<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\TaxRate;

class UpdateTaxRate
{
    /**
     * @param  array{name?: string, rate?: float|string, is_active?: bool}  $data
     */
    public function execute(TaxRate $taxRate, User $user, array $data): TaxRate
    {
        $taxRate->fill(array_filter([
            'name' => $data['name'] ?? null,
            'rate' => $data['rate'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        $taxRate->updated_by = $user->id;
        $taxRate->save();

        return $taxRate;
    }
}

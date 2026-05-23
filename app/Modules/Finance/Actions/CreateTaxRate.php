<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\TaxRate;

class CreateTaxRate
{
    /**
     * @param  array{name: string, type: string, rate: float|string, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): TaxRate
    {
        return TaxRate::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'type' => $data['type'],
            'rate' => $data['rate'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}

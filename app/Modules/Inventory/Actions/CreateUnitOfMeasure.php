<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;

class CreateUnitOfMeasure
{
    /**
     * @param  array{name: string, code: string, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => $data['code'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}

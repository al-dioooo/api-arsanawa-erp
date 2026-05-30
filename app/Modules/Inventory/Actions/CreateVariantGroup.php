<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\VariantGroup;

class CreateVariantGroup
{
    /**
     * @param  array{name: string, code: string, unit_of_measure_id: int, description?: ?string, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): VariantGroup
    {
        return VariantGroup::create([
            'company_id' => $companyId,
            'unit_of_measure_id' => $data['unit_of_measure_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ])->load(['unit', 'variants']);
    }
}

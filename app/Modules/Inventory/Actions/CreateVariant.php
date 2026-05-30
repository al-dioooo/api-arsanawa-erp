<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Variant;

class CreateVariant
{
    /**
     * @param  array{variant_group_id: int, name: string, code: string, position?: int, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): Variant
    {
        return Variant::create([
            'company_id' => $companyId,
            'variant_group_id' => $data['variant_group_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'position' => $data['position'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ])->load('group.unit');
    }
}

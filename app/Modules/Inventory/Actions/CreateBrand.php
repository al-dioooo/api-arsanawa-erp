<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Brand;

class CreateBrand
{
    /**
     * @param  array{name: string, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): Brand
    {
        return Brand::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}

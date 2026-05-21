<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Brand;

class UpdateBrand
{
    /**
     * @param  array{name?: string, is_active?: bool}  $data
     */
    public function execute(Brand $brand, User $user, array $data): Brand
    {
        $brand->fill(array_intersect_key($data, array_flip(['name', 'is_active'])));
        $brand->updated_by = $user->id;
        $brand->save();

        return $brand;
    }
}

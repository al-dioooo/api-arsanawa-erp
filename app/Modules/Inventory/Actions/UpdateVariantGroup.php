<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\VariantGroup;

class UpdateVariantGroup
{
    /**
     * @param  array{name?: string, code?: string, unit_of_measure_id?: int, description?: ?string, is_active?: bool}  $data
     */
    public function execute(VariantGroup $variantGroup, User $user, array $data): VariantGroup
    {
        $variantGroup->fill($data);
        $variantGroup->updated_by = $user->id;
        $variantGroup->save();

        return $variantGroup->load(['unit', 'variants']);
    }
}

<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Variant;

class UpdateVariant
{
    /**
     * @param  array{variant_group_id?: int, name?: string, code?: string, position?: int, is_active?: bool}  $data
     */
    public function execute(Variant $variant, User $user, array $data): Variant
    {
        $variant->fill($data);
        $variant->updated_by = $user->id;
        $variant->save();

        return $variant->load('group.unit');
    }
}

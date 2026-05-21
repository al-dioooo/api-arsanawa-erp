<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;

class UpdateUnitOfMeasure
{
    /**
     * @param  array{name?: string, code?: string, is_active?: bool}  $data
     */
    public function execute(UnitOfMeasure $unit, User $user, array $data): UnitOfMeasure
    {
        $unit->fill(array_intersect_key($data, array_flip(['name', 'code', 'is_active'])));
        $unit->updated_by = $user->id;
        $unit->save();

        return $unit;
    }
}

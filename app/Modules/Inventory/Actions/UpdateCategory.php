<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Category;

class UpdateCategory
{
    /**
     * @param  array{name?: string, position?: int, is_active?: bool}  $data
     */
    public function execute(Category $category, User $user, array $data): Category
    {
        $category->fill(array_intersect_key($data, array_flip(['name', 'position', 'is_active'])));
        $category->updated_by = $user->id;
        $category->save();

        return $category;
    }
}

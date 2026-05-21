<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Category;

class DeleteCategory
{
    public function execute(Category $category): void
    {
        $category->delete();
    }
}

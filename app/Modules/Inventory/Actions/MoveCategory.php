<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Services\CategoryTree;
use Illuminate\Support\Facades\DB;

class MoveCategory
{
    public function __construct(private readonly CategoryTree $tree) {}

    public function execute(Category $category, ?Category $newParent, User $user): Category
    {
        return DB::transaction(function () use ($category, $newParent, $user): Category {
            $this->tree->reposition($category, $newParent);

            $category->updated_by = $user->id;
            $category->save();

            return $category->refresh();
        });
    }
}

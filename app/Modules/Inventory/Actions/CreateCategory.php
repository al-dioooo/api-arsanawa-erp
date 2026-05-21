<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Services\CategoryTree;
use Illuminate\Support\Facades\DB;

class CreateCategory
{
    public function __construct(private readonly CategoryTree $tree) {}

    /**
     * @param  array{name: string, parent_id?: int|null, position?: int, is_active?: bool}  $data
     */
    public function execute(int $companyId, User $user, array $data): Category
    {
        return DB::transaction(function () use ($companyId, $user, $data): Category {
            $parent = isset($data['parent_id'])
                ? Category::query()->forCompany($companyId)->findOrFail($data['parent_id'])
                : null;

            $category = Category::create([
                'company_id' => $companyId,
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'path' => '',
                'depth' => 0,
                'position' => $data['position'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $category->path = $this->tree->pathFor($parent, $category->id);
            $category->depth = $this->tree->depthFor($parent);
            $category->save();

            return $category;
        });
    }
}

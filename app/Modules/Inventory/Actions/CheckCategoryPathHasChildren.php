<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Category;

class CheckCategoryPathHasChildren
{
    /**
     * Read contract for other modules: whether a ">"-separated category path
     * resolves to a category that still has children.
     *
     * False when the path does not resolve at all, so callers can treat "not
     * found" and "is a leaf" alike — only a real parent blocks assignment.
     */
    public function execute(int $companyId, string $path): bool
    {
        $category = $this->resolve($companyId, $path);

        if ($category === null) {
            return false;
        }

        return Category::query()
            ->forCompany($companyId)
            ->where('parent_id', $category->id)
            ->exists();
    }

    private function resolve(int $companyId, string $path): ?Category
    {
        $parent = null;
        $category = null;

        foreach (array_filter(array_map('trim', explode('>', $path))) as $name) {
            $category = Category::query()
                ->where('company_id', $companyId)
                ->where('parent_id', $parent?->id)
                ->where('name', $name)
                ->first();

            if ($category === null) {
                return null;
            }

            $parent = $category;
        }

        return $category;
    }
}

<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Category;

/**
 * Maintains the materialized `path` and `depth` of the category tree.
 * A path is slash-delimited and slash-terminated, e.g. "/4/9/12/".
 */
class CategoryTree
{
    public function pathFor(?Category $parent, int $id): string
    {
        $base = $parent?->path ?: '/';

        return $base.$id.'/';
    }

    public function depthFor(?Category $parent): int
    {
        return $parent !== null ? $parent->depth + 1 : 0;
    }

    /**
     * Re-parent a category, recomputing its path/depth and cascading to descendants.
     */
    public function reposition(Category $category, ?Category $newParent): void
    {
        $oldPath = $category->path;
        $newPath = $this->pathFor($newParent, $category->id);
        $depthDelta = $this->depthFor($newParent) - $category->depth;

        $descendants = Category::query()
            ->where('company_id', $category->company_id)
            ->where('path', 'like', $oldPath.'%')
            ->where('id', '!=', $category->id)
            ->get();

        $category->parent_id = $newParent?->id;
        $category->path = $newPath;
        $category->depth = $this->depthFor($newParent);
        $category->save();

        foreach ($descendants as $descendant) {
            $descendant->path = $newPath.substr($descendant->path, strlen($oldPath));
            $descendant->depth += $depthDelta;
            $descendant->save();
        }
    }

    /**
     * True when moving $category under $newParent would create a cycle —
     * i.e. the new parent is the category itself or one of its descendants.
     */
    public function wouldCreateCycle(Category $category, ?Category $newParent): bool
    {
        if ($newParent === null) {
            return false;
        }

        return $newParent->id === $category->id
            || str_starts_with($newParent->path, $category->path);
    }
}

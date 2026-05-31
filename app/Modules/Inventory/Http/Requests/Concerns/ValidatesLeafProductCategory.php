<?php

namespace App\Modules\Inventory\Http\Requests\Concerns;

use App\Modules\Inventory\Models\Category;
use Closure;

trait ValidatesLeafProductCategory
{
    protected function leafProductCategoryRule(?int $companyId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($companyId): void {
            if ($value === null || $value === '' || $companyId === null) {
                return;
            }

            $hasChildren = Category::query()
                ->forCompany($companyId)
                ->where('parent_id', (int) $value)
                ->exists();

            if ($hasChildren) {
                $fail(__('Products can only be assigned to the lowest category level.'));
            }
        };
    }
}

<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Services\CategoryTree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCategoryRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('company_id', $this->activeCompanyId()),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->activeCompanyId();

            $category = Category::query()
                ->where('company_id', $companyId)
                ->find((int) $this->route('category'));

            if ($category === null) {
                return;
            }

            $parentId = $this->input('parent_id');
            $parent = $parentId !== null
                ? Category::query()->where('company_id', $companyId)->find((int) $parentId)
                : null;

            if (app(CategoryTree::class)->wouldCreateCycle($category, $parent)) {
                $validator->errors()->add(
                    'parent_id',
                    __('A category cannot be moved under itself or its own descendant.'),
                );
            }
        });
    }
}

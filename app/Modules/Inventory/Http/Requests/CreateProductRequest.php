<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Http\Requests\Concerns\ValidatesLeafProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    use AuthorizesInventoryRequests;
    use ValidatesLeafProductCategory;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'base_uom_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where('company_id', $companyId),
            ],
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('company_id', $companyId),
                $this->leafProductCategoryRule($companyId),
            ],
            'brand_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('company_id', $companyId),
            ],
            'track_stock' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.sku' => [
                'required',
                'string',
                'max:80',
                'distinct',
                Rule::unique('product_variants', 'sku')->where('company_id', $companyId),
            ],
            'variants.*.barcode' => ['sometimes', 'nullable', 'string', 'max:80'],
            'variants.*.name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'variants.*.attributes' => ['sometimes', 'nullable', 'array'],
            'variants.*.purchase_uom_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('units_of_measure', 'id')->where('company_id', $companyId),
            ],
            'variants.*.purchase_conversion_factor' => ['sometimes', 'numeric', 'min:0.0001'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}

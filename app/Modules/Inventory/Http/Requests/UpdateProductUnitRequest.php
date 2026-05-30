<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductUnitRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();
        $productUnit = (int) $this->route('productUnit');

        return [
            'product_id' => ['sometimes', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'sku' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('product_units', 'sku')->where('company_id', $companyId)->ignore($productUnit),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:80'],
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'variant_ids' => ['sometimes', 'array'],
            'variant_ids.*' => ['integer', 'distinct', Rule::exists('variants', 'id')->where('company_id', $companyId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

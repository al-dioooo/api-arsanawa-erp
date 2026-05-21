<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddVariantRequest extends FormRequest
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
        $companyId = $this->activeCompanyId();

        return [
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('product_variants', 'sku')->where('company_id', $companyId),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:80'],
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'purchase_uom_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('units_of_measure', 'id')->where('company_id', $companyId),
            ],
            'purchase_conversion_factor' => ['sometimes', 'numeric', 'min:0.0001'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

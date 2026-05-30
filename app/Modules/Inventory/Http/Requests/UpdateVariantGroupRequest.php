<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantGroupRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();
        $variantGroup = (int) $this->route('variantGroup');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('variant_groups', 'code')->where('company_id', $companyId)->ignore($variantGroup),
            ],
            'unit_of_measure_id' => [
                'sometimes',
                'integer',
                Rule::exists('units_of_measure', 'id')->where('company_id', $companyId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

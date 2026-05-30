<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVariantGroupRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('variant_groups', 'code')->where('company_id', $companyId),
            ],
            'unit_of_measure_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where('company_id', $companyId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

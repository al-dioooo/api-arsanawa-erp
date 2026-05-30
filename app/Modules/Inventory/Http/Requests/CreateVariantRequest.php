<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVariantRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    public function rules(): array
    {
        $groupId = (int) $this->input('variant_group_id');

        return [
            'variant_group_id' => [
                'required',
                'integer',
                Rule::exists('variant_groups', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('variants', 'code')->where('variant_group_id', $groupId),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

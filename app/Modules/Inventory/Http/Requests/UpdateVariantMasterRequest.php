<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantMasterRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-products');
    }

    public function rules(): array
    {
        $groupId = (int) ($this->input('variant_group_id') ?? 0);
        $variant = (int) $this->route('variant');

        return [
            'variant_group_id' => [
                'sometimes',
                'integer',
                Rule::exists('variant_groups', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('variants', 'code')->where('variant_group_id', $groupId)->ignore($variant),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

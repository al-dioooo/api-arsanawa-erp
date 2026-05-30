<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVariantsRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.view');
    }

    public function rules(): array
    {
        return [
            'variant_group_id' => [
                'sometimes',
                'integer',
                Rule::exists('variant_groups', 'id')->where('company_id', $this->activeCompanyId()),
            ],
        ];
    }
}

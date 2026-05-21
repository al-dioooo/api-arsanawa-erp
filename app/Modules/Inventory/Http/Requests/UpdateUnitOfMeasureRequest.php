<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('units_of_measure', 'code')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($this->route('unit')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSalesRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.view') || $this->canInActiveCompany('pos.operate');
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['counter', 'catering'])],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'confirmed', 'completed', 'void'])],
            'branch_id' => [
                'sometimes',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'fulfilment_from' => ['sometimes', 'date'],
            'fulfilment_to' => ['sometimes', 'date', 'after_or_equal:fulfilment_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

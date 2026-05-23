<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRegistersRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.manage-registers');
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'sometimes',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

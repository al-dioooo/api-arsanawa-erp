<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListShiftsRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.operate');
    }

    public function rules(): array
    {
        return [
            'register_id' => [
                'sometimes',
                'integer',
                Rule::exists('registers', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'status' => ['sometimes', 'string', Rule::in(['open', 'closed'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

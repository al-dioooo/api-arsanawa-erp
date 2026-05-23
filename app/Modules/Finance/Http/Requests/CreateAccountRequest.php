<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAccountRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-accounts');
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('chart_of_accounts', 'code')->where('company_id', $this->activeCompanyId()),
            ],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

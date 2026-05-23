<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegisterRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.manage-registers');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();
        $registerId = $this->route('register');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('registers', 'code')->where('company_id', $companyId)->ignore($registerId),
            ],
            'branch_id' => [
                'sometimes',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'cash_account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_postable', true),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

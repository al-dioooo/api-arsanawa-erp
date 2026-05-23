<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class UpsertAccountMappingsRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-accounts');
    }

    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.key' => ['required', 'string'],
            'mappings.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}

<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetTrialBalanceRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.view');
    }

    public function rules(): array
    {
        return [
            'accounting_period_id' => [
                'required',
                'integer',
                Rule::exists('accounting_periods', 'id')->where('company_id', $this->activeCompanyId()),
            ],
        ];
    }
}

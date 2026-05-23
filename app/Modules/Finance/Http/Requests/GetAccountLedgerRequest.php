<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetAccountLedgerRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.view');
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'accounting_period_id' => [
                'required',
                'integer',
                Rule::exists('accounting_periods', 'id')->where('company_id', $this->activeCompanyId()),
            ],
        ];
    }
}

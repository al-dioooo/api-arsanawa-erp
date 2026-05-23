<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-payments');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'partner_id' => [
                'required',
                'integer',
                Rule::exists('partners', 'id')->where('company_id', $companyId),
            ],
            'branch_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'payment_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('payments', 'payment_number')->where('company_id', $companyId),
            ],
            'payment_type' => ['required', 'string', Rule::in(['inbound', 'outbound'])],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cash_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_postable', true)
                    ->where('is_active', true)
                    ->where('type', 'asset'),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.invoice_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('invoices', 'id')->where('company_id', $companyId),
            ],
            'allocations.*.bill_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('bills', 'id')->where('company_id', $companyId),
            ],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],
        ];
    }
}

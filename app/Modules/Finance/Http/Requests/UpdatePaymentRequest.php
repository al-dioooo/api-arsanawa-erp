<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use App\Modules\Finance\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-payments');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();
        $paymentId = $this->route('payment');

        if ($paymentId instanceof Payment) {
            $paymentId = $paymentId->id;
        }

        return [
            'partner_id' => [
                'sometimes',
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
                'string',
                'max:255',
                Rule::unique('payments', 'payment_number')
                    ->where('company_id', $companyId)
                    ->ignore($paymentId),
            ],
            'payment_type' => ['sometimes', 'string', Rule::in(['inbound', 'outbound'])],
            'payment_date' => ['sometimes', 'date'],
            'payment_method' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0'],
            'cash_account_id' => [
                'sometimes',
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

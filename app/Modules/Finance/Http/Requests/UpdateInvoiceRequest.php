<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-receivables');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();
        $invoiceId = $this->route('invoice');

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
            'invoice_number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('invoices', 'invoice_number')
                    ->where('company_id', $companyId)
                    ->ignore($invoiceId),
            ],
            'invoice_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:invoice_date'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'lines.*.revenue_account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_postable', true),
            ],
            'lines.*.description' => ['required_with:lines', 'string', 'max:255'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('tax_rates', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}

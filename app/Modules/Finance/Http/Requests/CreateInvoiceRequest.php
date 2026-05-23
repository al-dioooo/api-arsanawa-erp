<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-receivables');
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
            'invoice_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('invoices', 'invoice_number')->where('company_id', $companyId),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['sometimes', 'nullable', 'integer', 'exists:product_variants,id'],
            'lines.*.revenue_account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_postable', true),
            ],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
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

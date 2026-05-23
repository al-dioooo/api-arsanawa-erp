<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $branchId = (int) $this->input('branch_id');

        return $branchId > 0 && $this->canInBranch('pos.operate', $branchId);
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'type' => ['required', 'string', Rule::in(['counter', 'catering'])],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'register_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('registers', 'id')->where('company_id', $companyId),
            ],
            'cashier_shift_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('cashier_shifts', 'id')->where('company_id', $companyId),
            ],
            'sale_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('sales', 'sale_number')->where('company_id', $companyId),
            ],
            'partner_id' => [
                'required_if:type,catering',
                'nullable',
                'integer',
                Rule::exists('partners', 'id')->where('company_id', $companyId),
            ],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'order_date' => ['sometimes', 'date'],
            'fulfilment_date' => ['required_if:type,catering', 'nullable', 'date'],
            'delivery_address' => ['sometimes', 'nullable', 'string'],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->where('company_id', $companyId),
            ],
            'lines.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('tax_rates', 'id')->where('company_id', $companyId),
            ],
            'lines.*.revenue_account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}

<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Models\Sale;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends StoreSaleRequest
{
    public function authorize(): bool
    {
        $sale = Sale::query()
            ->forCompany((int) $this->activeCompanyId())
            ->find($this->route('sale'));

        return $sale !== null && $this->canInBranch('pos.operate', $sale->branch_id);
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();
        $saleId = $this->route('sale');

        return [
            'type' => ['sometimes', 'string', Rule::in(['counter', 'catering'])],
            'branch_id' => [
                'sometimes',
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
                Rule::unique('sales', 'sale_number')->where('company_id', $companyId)->ignore($saleId),
            ],
            'partner_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('partners', 'id')->where('company_id', $companyId),
            ],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'order_date' => ['sometimes', 'date'],
            'fulfilment_date' => ['sometimes', 'nullable', 'date'],
            'fulfilment_time_window' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery_address' => ['sometimes', 'nullable', 'string'],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.product_variant_id' => [
                'required_with:lines',
                'integer',
                Rule::exists('product_variants', 'id')->where('company_id', $companyId),
            ],
            'lines.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
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

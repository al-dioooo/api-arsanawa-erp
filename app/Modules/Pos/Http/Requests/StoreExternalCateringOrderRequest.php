<?php

namespace App\Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalCateringOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('external_api_key');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $companyId = (int) $this->attributes->get('active_company_id');

        return [
            'external_reference' => ['required', 'string', 'max:160'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'customer.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'order_date' => ['sometimes', 'date'],
            'fulfilment_date' => ['required', 'date'],
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
        ];
    }
}

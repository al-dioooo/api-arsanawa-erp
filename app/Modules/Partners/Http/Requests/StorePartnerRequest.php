<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Partners\Http\Requests\Concerns\AuthorizesPartnerRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    use AuthorizesPartnerRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('partners.create');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['customer', 'supplier', 'both'])],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('partners', 'code')->where('company_id', $this->activeCompanyId())],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tax_identifier' => ['sometimes', 'nullable', 'string', 'max:50'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'transaction_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.role' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contacts.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],
            'addresses' => ['sometimes', 'array'],
            'addresses.*.type' => ['sometimes', 'string', Rule::in(['billing', 'shipping', 'other'])],
            'addresses.*.label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'addresses.*.address_line_1' => ['required', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'addresses.*.city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'addresses.*.province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'addresses.*.postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'addresses.*.country' => ['sometimes', 'nullable', 'string', 'max:2'],
            'addresses.*.is_default' => ['sometimes', 'boolean'],
        ];
    }
}

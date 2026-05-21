<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Partners\Http\Requests\Concerns\AuthorizesPartnerRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    use AuthorizesPartnerRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('partners.update');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $partnerId = $this->route('partner');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(['customer', 'supplier', 'both'])],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('partners', 'code')->where('company_id', $this->activeCompanyId())->ignore($partnerId)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tax_identifier' => ['sometimes', 'nullable', 'string', 'max:50'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'transaction_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}

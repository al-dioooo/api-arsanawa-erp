<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Partners\Http\Requests\Concerns\AuthorizesPartnerRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerAddressRequest extends FormRequest
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
        return [
            'type' => ['sometimes', 'string', Rule::in(['billing', 'shipping', 'other'])],
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address_line_1' => ['sometimes', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:2'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}

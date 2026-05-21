<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Partners\Http\Requests\Concerns\AuthorizesPartnerRequests;
use Illuminate\Foundation\Http\FormRequest;

class ListPartnersRequest extends FormRequest
{
    use AuthorizesPartnerRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('partners.view');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:customer,supplier,both'],
            'status' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

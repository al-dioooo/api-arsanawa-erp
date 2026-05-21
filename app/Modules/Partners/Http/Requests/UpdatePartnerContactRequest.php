<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Partners\Http\Requests\Concerns\AuthorizesPartnerRequests;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerContactRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}

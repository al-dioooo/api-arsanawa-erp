<?php

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['sometimes', 'string', 'max:180', 'alpha_dash:ascii', Rule::unique('companies', 'slug')],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'tax_identifier' => ['sometimes', 'nullable', 'string', 'max:80'],
            'primary_branch_name' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }
}

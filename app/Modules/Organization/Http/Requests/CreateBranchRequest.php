<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesOrganizationRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBranchRequest extends FormRequest
{
    use AuthorizesOrganizationRequests;

    public function authorize(): bool
    {
        return $this->canForRouteCompany('organization.manage-branches');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $company = $this->routeCompany();

        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
                'alpha_dash:ascii',
                Rule::unique('branches', 'code')->where('company_id', $company?->id),
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}

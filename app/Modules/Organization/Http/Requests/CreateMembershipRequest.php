<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesOrganizationRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMembershipRequest extends FormRequest
{
    use AuthorizesOrganizationRequests;

    public function authorize(): bool
    {
        return $this->canForRouteCompany('organization.manage-members');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $company = $this->routeCompany();

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('memberships', 'user_id')->where('company_id', $company?->id),
            ],
            'branch_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $company?->id),
            ],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'member'])],
        ];
    }
}

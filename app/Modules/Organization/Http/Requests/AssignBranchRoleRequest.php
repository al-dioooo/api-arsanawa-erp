<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesOrganizationRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignBranchRoleRequest extends FormRequest
{
    use AuthorizesOrganizationRequests;

    public function authorize(): bool
    {
        return $this->canForRouteCompany('organization.manage-members');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->routeCompany()?->id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'user_id')
                    ->where('company_id', $companyId)
                    ->where('status', 'active'),
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where('team_id', $companyId)
                    ->where('guard_name', 'api'),
            ],
            'status' => ['sometimes', 'string', 'in:active,suspended'],
        ];
    }
}

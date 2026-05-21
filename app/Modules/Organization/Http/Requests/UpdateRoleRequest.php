<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesOrganizationRequests;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use AuthorizesOrganizationRequests;

    public function authorize(): bool
    {
        return $this->canForRouteCompany('organization.manage-roles');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->routeCompany()?->id;
        $roleId = $this->route('role')?->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')
                    ->where('team_id', $companyId)
                    ->where('guard_name', 'api')
                    ->ignore($roleId),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ];
    }
}

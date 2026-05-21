<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesOrganizationRequests;
use Illuminate\Foundation\Http\FormRequest;

class ManageBranchRolesRequest extends FormRequest
{
    use AuthorizesOrganizationRequests;

    public function authorize(): bool
    {
        return $this->canForRouteCompany('organization.manage-members');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}

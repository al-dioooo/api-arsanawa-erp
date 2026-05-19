<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesOrganizationRequests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleEntitlementsRequest extends FormRequest
{
    use AuthorizesOrganizationRequests;

    public function authorize(): bool
    {
        return $this->canForRouteCompany('organization.manage-entitlements');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module' => ['required', 'string', 'max:80', 'alpha_dash:ascii', 'distinct'],
            'modules.*.is_enabled' => ['required', 'boolean'],
            'modules.*.expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}

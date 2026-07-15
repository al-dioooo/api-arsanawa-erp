<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Services\DeveloperAccess;
use Illuminate\Foundation\Http\FormRequest;

class ViewPermissionCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $companyId = $this->attributes->get('active_company_id');

        if ($companyId === null) {
            return false;
        }

        setPermissionsTeamId((int) $companyId);

        if (app(DeveloperAccess::class)->userIsDeveloper($this->user())) {
            return true;
        }

        // The catalog is only needed by role editors, so gate it accordingly.
        return $this->user()?->can('organization.manage-roles') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

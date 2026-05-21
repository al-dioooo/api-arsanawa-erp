<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRewardRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        if (! $this->canInActiveCompany('inventory.manage-promotions')) {
            return false;
        }

        $companyId = $this->activeCompanyId();

        return (bool) app(SettingsManager::class)->get($companyId, 'inventory', 'rewards_enabled', true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}

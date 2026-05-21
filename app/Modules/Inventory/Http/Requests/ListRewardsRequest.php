<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Foundation\Http\FormRequest;

class ListRewardsRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        if (! $this->canInActiveCompany('inventory.view')) {
            return false;
        }

        $companyId = $this->activeCompanyId();

        return (bool) app(SettingsManager::class)->get($companyId, 'inventory', 'rewards_enabled', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Foundation\Http\FormRequest;

class ListDiscountsRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        if (! $this->canInActiveCompany('inventory.view')) {
            return false;
        }

        $companyId = $this->activeCompanyId();
        $enabled = app(SettingsManager::class)->get($companyId, 'inventory', 'discounts_enabled', true);

        return (bool) $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

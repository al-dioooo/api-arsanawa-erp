<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDiscountRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        if (! $this->canInActiveCompany('inventory.manage-promotions')) {
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
        return [
            'name' => ['required', 'string', 'max:200'],
            'calculation_type' => ['required', 'string', Rule::in(['percentage', 'amount'])],
            'value' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starting_item_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'multiply' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'targets' => ['sometimes', 'array'],
            'targets.*.target_type' => ['required_with:targets', 'string', Rule::in(['variant', 'product', 'category'])],
            'targets.*.target_id' => ['required_with:targets', 'integer'],
            'dependencies' => ['sometimes', 'array'],
            'dependencies.*.product_variant_id' => ['required_with:dependencies', 'integer'],
            'dependencies.*.required_quantity' => ['required_with:dependencies', 'integer', 'min:1'],
            'giveaways' => ['sometimes', 'array'],
            'giveaways.*.product_variant_id' => ['required_with:giveaways', 'integer'],
            'giveaways.*.giveaway_quantity' => ['required_with:giveaways', 'integer', 'min:1'],
        ];
    }
}

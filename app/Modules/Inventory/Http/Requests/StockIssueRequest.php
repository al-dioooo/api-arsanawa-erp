<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use App\Modules\Platform\Services\CateringMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockIssueRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        $companyId = $this->activeCompanyId();

        if ($companyId !== null && app(CateringMode::class)->inventoryRestricted($companyId)) {
            return false;
        }

        return $this->canInActiveCompany('inventory.manage-stock');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'product_unit_id' => [
                'required_without:product_variant_id',
                'integer',
                Rule::exists('product_units', 'id')->where('company_id', $companyId),
            ],
            'product_variant_id' => [
                'required_without:product_unit_id',
                'integer',
                Rule::exists('product_variants', 'id')->where('company_id', $companyId),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockQueryRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'product_unit_id' => ['sometimes', 'integer', Rule::exists('product_units', 'id')->where('company_id', $companyId)],
            'product_variant_id' => ['sometimes', 'integer', Rule::exists('product_variants', 'id')->where('company_id', $companyId)],
            'branch_id' => ['sometimes', 'integer'],
            'expiring_before' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

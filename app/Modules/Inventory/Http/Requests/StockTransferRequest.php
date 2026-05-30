<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.manage-stock');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'from_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'to_branch_id' => [
                'required',
                'integer',
                'different:from_branch_id',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_unit_id' => [
                'required_without:items.*.product_variant_id',
                'integer',
                Rule::exists('product_units', 'id')->where('company_id', $companyId),
            ],
            'items.*.product_variant_id' => [
                'required_without:items.*.product_unit_id',
                'integer',
                Rule::exists('product_variants', 'id')->where('company_id', $companyId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

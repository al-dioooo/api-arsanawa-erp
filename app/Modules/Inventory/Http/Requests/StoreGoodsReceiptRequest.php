<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceiptRequest extends FormRequest
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
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'partner_id' => [
                'required',
                'integer',
                Rule::exists('partners', 'id')->where('company_id', $companyId),
            ],
            'receipt_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('goods_receipts', 'receipt_number')->where('company_id', $companyId),
            ],
            'delivery_note_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'receipt_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_unit_id' => [
                'required_without:lines.*.product_variant_id',
                'nullable',
                'integer',
                Rule::exists('product_units', 'id')->where('company_id', $companyId),
            ],
            'lines.*.product_variant_id' => [
                'required_without:lines.*.product_unit_id',
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('company_id', $companyId),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.lot_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lines.*.expiry_date' => ['sometimes', 'nullable', 'date'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

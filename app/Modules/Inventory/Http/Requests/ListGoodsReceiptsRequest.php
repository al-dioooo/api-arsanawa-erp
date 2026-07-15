<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGoodsReceiptsRequest extends FormRequest
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
            'partner_id' => ['sometimes', 'integer', Rule::exists('partners', 'id')->where('company_id', $companyId)],
            'branch_id' => ['sometimes', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'status' => ['sometimes', 'string', Rule::in(['received', 'completed', 'void'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

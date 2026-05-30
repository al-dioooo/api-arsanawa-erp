<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductUnitsRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.view');
    }

    public function rules(): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'product_id' => ['sometimes', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'brand_id' => ['sometimes', 'integer', Rule::exists('brands', 'id')->where('company_id', $companyId)],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

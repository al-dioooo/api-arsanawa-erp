<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRateRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-tax');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

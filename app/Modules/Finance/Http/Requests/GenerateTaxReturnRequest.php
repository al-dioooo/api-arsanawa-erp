<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTaxReturnRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-tax');
    }

    public function rules(): array
    {
        return [
            'tax_type' => ['required', 'string', 'in:vat,withholding'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ];
    }
}

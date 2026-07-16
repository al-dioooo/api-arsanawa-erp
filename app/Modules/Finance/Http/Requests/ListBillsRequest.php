<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class ListBillsRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.view') || $this->canInActiveCompany('finance.manage-payables');
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'string', 'in:draft,posted,partially_paid,paid,void'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

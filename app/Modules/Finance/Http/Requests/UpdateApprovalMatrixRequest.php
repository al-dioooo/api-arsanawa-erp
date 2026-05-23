<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalMatrixRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.manage-accounts');
    }

    public function rules(): array
    {
        return [
            'document_type' => ['sometimes', 'string', Rule::in(['bill', 'payment'])],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'numeric', 'min:0', $this->has('min_amount') ? 'gte:min_amount' : ''],
            'level' => ['sometimes', 'integer', 'min:1'],
            'approver_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}

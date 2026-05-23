<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class ActApprovalRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.approve');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approved,rejected'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

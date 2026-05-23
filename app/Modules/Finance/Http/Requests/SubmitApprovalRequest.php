<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class SubmitApprovalRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        if ($this->route('bill')) {
            return $this->canInActiveCompany('finance.manage-payables');
        }
        if ($this->route('payment')) {
            return $this->canInActiveCompany('finance.manage-payments');
        }

        return false;
    }

    public function rules(): array
    {
        return [];
    }
}

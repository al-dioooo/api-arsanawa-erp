<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Http\Requests\Concerns\AuthorizesFinanceRequests;
use Illuminate\Foundation\Http\FormRequest;

class ShowFinanceDashboardRequest extends FormRequest
{
    use AuthorizesFinanceRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('finance.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

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
        return [
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}

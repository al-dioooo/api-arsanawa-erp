<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetSalesReportRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.view-reports');
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => [
                'sometimes',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $this->activeCompanyId()),
            ],
        ];
    }
}

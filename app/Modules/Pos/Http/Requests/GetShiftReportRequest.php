<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;

class GetShiftReportRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.view-reports');
    }

    public function rules(): array
    {
        return [];
    }
}

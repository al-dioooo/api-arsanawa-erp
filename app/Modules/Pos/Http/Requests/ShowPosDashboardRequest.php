<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use Illuminate\Foundation\Http\FormRequest;

class ShowPosDashboardRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('pos.view') || $this->canInActiveCompany('pos.operate');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

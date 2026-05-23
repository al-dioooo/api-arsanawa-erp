<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use App\Modules\Pos\Models\Register;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenShiftRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $companyId = $this->activeCompanyId();

        if ($companyId === null) {
            return false;
        }

        $register = Register::query()
            ->forCompany($companyId)
            ->find($this->input('register_id'));

        return $register !== null && $this->canInBranch('pos.operate', $register->branch_id);
    }

    public function rules(): array
    {
        return [
            'register_id' => [
                'required',
                'integer',
                Rule::exists('registers', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'opening_float' => ['required', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use App\Modules\Pos\Models\CashierShift;
use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $shift = CashierShift::query()
            ->forCompany((int) $this->activeCompanyId())
            ->find($this->route('shift'));

        return $shift !== null && $this->canInBranch('pos.operate', $shift->branch_id);
    }

    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

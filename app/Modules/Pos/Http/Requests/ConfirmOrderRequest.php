<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use App\Modules\Pos\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmOrderRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $sale = Sale::query()
            ->forCompany((int) $this->activeCompanyId())
            ->find($this->route('sale'));

        return $sale !== null && ($this->canInActiveCompany('pos.manage-orders') || $this->canInBranch('pos.manage-orders', $sale->branch_id));
    }

    public function rules(): array
    {
        return [];
    }
}

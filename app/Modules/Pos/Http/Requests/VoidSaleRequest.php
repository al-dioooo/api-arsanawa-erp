<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use App\Modules\Pos\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $sale = Sale::query()
            ->forCompany((int) $this->activeCompanyId())
            ->find($this->route('sale'));

        if ($sale === null) {
            return false;
        }

        return $this->canInActiveCompany('pos.void-sales') || $this->canInBranch('pos.void-sales', $sale->branch_id);
    }

    public function rules(): array
    {
        return [];
    }
}

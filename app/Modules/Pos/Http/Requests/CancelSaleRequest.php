<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use App\Modules\Pos\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class CancelSaleRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $sale = Sale::query()
            ->forCompany((int) $this->activeCompanyId())
            ->find($this->route('sale'));

        if ($sale === null) {
            return true;
        }

        return $this->canInBranch('pos.operate', $sale->branch_id);
    }

    public function rules(): array
    {
        return [];
    }
}

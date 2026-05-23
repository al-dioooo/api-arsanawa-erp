<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Http\Requests\Concerns\AuthorizesPosRequests;
use App\Modules\Pos\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddSalePaymentRequest extends FormRequest
{
    use AuthorizesPosRequests;

    public function authorize(): bool
    {
        $sale = Sale::query()
            ->forCompany((int) $this->activeCompanyId())
            ->find($this->route('sale'));

        return $sale !== null && $this->canInBranch('pos.operate', $sale->branch_id);
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['cash', 'card', 'qris', 'transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}

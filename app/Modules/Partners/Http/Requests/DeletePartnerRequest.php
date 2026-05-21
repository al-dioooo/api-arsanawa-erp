<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Partners\Http\Requests\Concerns\AuthorizesPartnerRequests;
use Illuminate\Foundation\Http\FormRequest;

class DeletePartnerRequest extends FormRequest
{
    use AuthorizesPartnerRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('partners.delete');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}

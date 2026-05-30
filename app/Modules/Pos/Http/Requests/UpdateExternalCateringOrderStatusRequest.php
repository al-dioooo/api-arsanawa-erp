<?php

namespace App\Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalCateringOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('external_api_key');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['confirmed', 'void'])],
        ];
    }
}

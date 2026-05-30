<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesApiKeyManagement;
use Illuminate\Foundation\Http\FormRequest;

class CreateExternalApiKeyRequest extends FormRequest
{
    use AuthorizesApiKeyManagement;

    public function authorize(): bool
    {
        return $this->canManageApiKeys();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'source_channel' => ['required', 'string', 'max:120'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}

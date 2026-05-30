<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Http\Requests\Concerns\AuthorizesApiKeyManagement;
use Illuminate\Foundation\Http\FormRequest;

class ManageExternalApiKeysRequest extends FormRequest
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
        return [];
    }
}

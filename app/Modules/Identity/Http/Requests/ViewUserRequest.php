<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('identity.view');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}

<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Organization\Services\DeveloperAccess;
use Illuminate\Foundation\Http\FormRequest;

class ViewUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (app(DeveloperAccess::class)->userIsDeveloper($this->user())) {
            return true;
        }

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

<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Http\Requests\Concerns\AuthorizesPlatformRequests;
use Illuminate\Foundation\Http\FormRequest;

class ListSettingsRequest extends FormRequest
{
    use AuthorizesPlatformRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('platform.manage-settings');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'module' => ['sometimes', 'string', 'max:80'],
        ];
    }
}

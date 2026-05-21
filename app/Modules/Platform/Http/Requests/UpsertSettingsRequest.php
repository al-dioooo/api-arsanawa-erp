<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Http\Requests\Concerns\AuthorizesPlatformRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSettingsRequest extends FormRequest
{
    use AuthorizesPlatformRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('platform.manage-settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.module' => ['required', 'string', 'max:80'],
            'settings.*.key' => ['required', 'string', 'max:120'],
            'settings.*.value' => ['present'],
            'settings.*.branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $this->activeCompanyId()),
            ],
        ];
    }
}

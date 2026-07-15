<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Http\Requests\Concerns\AuthorizesPlatformRequests;
use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppTestRequest extends FormRequest
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
            'to' => ['required', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

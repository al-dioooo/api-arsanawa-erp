<?php

namespace App\Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewSpreadsheetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sheet_name' => ['required', 'string', 'max:255'],
        ];
    }
}

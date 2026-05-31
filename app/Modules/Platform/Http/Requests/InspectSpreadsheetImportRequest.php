<?php

namespace App\Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InspectSpreadsheetImportRequest extends FormRequest
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
            'file' => ['required_without:source_url', 'file', 'max:10240'],
            'source_url' => ['required_without:file', 'url', 'max:2048'],
        ];
    }
}

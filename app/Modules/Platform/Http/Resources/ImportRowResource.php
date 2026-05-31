<?php

namespace App\Modules\Platform\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'raw' => $this->raw,
            'normalized' => $this->normalized,
            'errors' => $this->errors ?? [],
        ];
    }
}

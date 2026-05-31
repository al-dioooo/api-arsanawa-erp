<?php

namespace App\Modules\Platform\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'kind' => $this->kind,
            'source' => $this->source,
            'original_name' => $this->original_name,
            'sheets' => $this->sheets ?? [],
            'selected_sheet' => $this->selected_sheet,
            'status' => $this->status,
            'row_count' => $this->row_count,
            'error_count' => $this->error_count,
            'created_count' => $this->created_count,
            'updated_count' => $this->updated_count,
            'failure_message' => $this->failure_message,
            'committed_at' => $this->committed_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'approvable_type' => $this->approvable_type,
            'approvable_id' => $this->approvable_id,
            'current_level' => $this->current_level,
            'status' => $this->status,
            'actions' => ApprovalActionResource::collection($this->whenLoaded('actions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

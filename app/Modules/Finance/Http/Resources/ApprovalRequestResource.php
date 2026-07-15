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
            'approvable' => $this->whenLoaded('approvable', function () {
                $document = $this->approvable;

                if ($document === null) {
                    return null;
                }

                return [
                    'id' => $document->id,
                    'bill_number' => $document->bill_number ?? null,
                    'payment_number' => $document->payment_number ?? null,
                    'total' => $document->total ?? null,
                    'amount' => $document->amount ?? null,
                    'status' => $document->status ?? null,
                ];
            }),
            'actions' => ApprovalActionResource::collection($this->whenLoaded('actions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

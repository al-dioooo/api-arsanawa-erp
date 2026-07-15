<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'receipt_number' => $this->receipt_number,
            'delivery_note_number' => $this->delivery_note_number,
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn (): array => [
                'id' => $this->partner->id,
                'name' => $this->partner->name,
            ]),
            'receipt_date' => $this->receipt_date instanceof Carbon
                ? $this->receipt_date->format('Y-m-d')
                : Carbon::parse($this->receipt_date)->format('Y-m-d'),
            'status' => $this->status,
            'total_cost' => $this->total_cost,
            'item_count' => $this->resolveItemCount(),
            'notes' => $this->notes,
            'bill_id' => $this->bill_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'lines' => GoodsReceiptLineResource::collection($this->whenLoaded('lines')),
        ];
    }

    /**
     * Prefer a withCount('lines') aggregate on list queries; fall back to the
     * loaded lines collection on the show endpoint. Null when neither is present.
     */
    private function resolveItemCount(): ?int
    {
        if ($this->lines_count !== null) {
            return (int) $this->lines_count;
        }

        if ($this->relationLoaded('lines')) {
            return $this->lines->count();
        }

        return null;
    }
}

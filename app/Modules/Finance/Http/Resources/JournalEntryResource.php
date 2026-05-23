<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date instanceof Carbon
                ? $this->entry_date->format('Y-m-d')
                : Carbon::parse($this->entry_date)->format('Y-m-d'),
            'accounting_period_id' => $this->accounting_period_id,
            'period' => new PeriodResource($this->whenLoaded('period')),
            'description' => $this->description,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'currency_id' => $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'status' => $this->status,
            'posted_at' => $this->posted_at instanceof Carbon
                ? $this->posted_at->toIso8601String()
                : ($this->posted_at ? Carbon::parse($this->posted_at)->toIso8601String() : null),
            'posted_by' => $this->posted_by,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'lines' => JournalLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}

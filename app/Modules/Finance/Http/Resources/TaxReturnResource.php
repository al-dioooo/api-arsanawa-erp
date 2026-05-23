<?php

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TaxReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'tax_type' => $this->tax_type,
            'period_start' => $this->period_start instanceof Carbon
                ? $this->period_start->format('Y-m-d')
                : Carbon::parse($this->period_start)->format('Y-m-d'),
            'period_end' => $this->period_end instanceof Carbon
                ? $this->period_end->format('Y-m-d')
                : Carbon::parse($this->period_end)->format('Y-m-d'),
            'status' => $this->status,
            'total_output' => $this->total_output,
            'total_input' => $this->total_input,
            'total_payable' => $this->total_payable,
            'journal_entry_id' => $this->journal_entry_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'lines' => TaxReturnLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}

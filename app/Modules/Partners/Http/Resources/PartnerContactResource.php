<?php

namespace App\Modules\Partners\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'name' => $this->name,
            'role' => $this->role,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_primary' => $this->is_primary,
        ];
    }
}

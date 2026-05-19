<?php

namespace App\Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleEntitlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'module' => $this->module,
            'is_enabled' => $this->is_enabled,
            'enabled_at' => $this->enabled_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}

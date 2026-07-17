<?php

namespace App\Modules\Platform\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSecret = $this->resource->isSecret();

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'module' => $this->module,
            'key' => $this->key,
            // Credentials are write-only: a client learns whether one is
            // configured, never what it is. Echoing this null back on upsert is
            // a no-op (see UpsertSettings), so a read-modify-write round trip
            // cannot silently wipe the stored credential.
            'value' => $isSecret ? null : $this->value,
            'is_secret' => $isSecret,
            // Derived without reading the value, so listing settings never has
            // to touch a credential just to report that one exists.
            'is_set' => $this->resource->hasValue(),
        ];
    }
}

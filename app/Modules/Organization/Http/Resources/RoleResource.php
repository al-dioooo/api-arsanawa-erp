<?php

namespace App\Modules\Organization\Http\Resources;

use App\Modules\Organization\Support\BuiltinRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_builtin' => BuiltinRoles::isBuiltin($this->name),
            'permissions' => $this->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}

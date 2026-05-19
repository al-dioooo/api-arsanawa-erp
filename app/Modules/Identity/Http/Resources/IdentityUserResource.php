<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\UserProfile;
use App\Modules\Identity\Models\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IdentityUserResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly UserProfile $profile,
        private readonly UserStatus $status,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'profile' => new UserProfileResource($this->profile),
            'status' => new UserStatusResource($this->status),
        ];
    }
}

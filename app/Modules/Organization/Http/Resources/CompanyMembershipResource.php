<?php

namespace App\Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'company' => new CompanyResource($this->resource['company']),
                'membership' => null,
            ];
        }

        return [
            'company' => new CompanyResource($this->company),
            'membership' => new MembershipResource($this),
        ];
    }
}

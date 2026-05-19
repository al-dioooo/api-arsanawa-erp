<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Illuminate\Database\Eloquent\Collection;

class GetOrganizationContext
{
    /**
     * Return the user's active company context.
     *
     * @return array{company: Company|null, membership: Membership|null, branches: Collection<int, Branch>}
     */
    public function execute(User $user, ?int $companyId): array
    {
        if (! $companyId) {
            return [
                'company' => null,
                'membership' => null,
                'branches' => new Collection,
            ];
        }

        $membership = Membership::query()
            ->with(['company', 'branch'])
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return [
                'company' => null,
                'membership' => null,
                'branches' => new Collection,
            ];
        }

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return [
            'company' => $membership->company,
            'membership' => $membership,
            'branches' => $branches,
        ];
    }
}

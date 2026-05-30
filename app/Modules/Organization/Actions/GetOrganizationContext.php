<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BranchAssignment;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Organization\Services\DeveloperAccess;
use Illuminate\Database\Eloquent\Collection;

class GetOrganizationContext
{
    public function __construct(private readonly DeveloperAccess $developerAccess) {}

    /**
     * Return the user's active company context.
     *
     * @return array{
     *     company: Company|null,
     *     membership: Membership|null,
     *     branches: Collection<int, Branch>,
     *     branch_assignments: Collection<int, BranchAssignment>
     * }
     */
    public function execute(User $user, ?int $companyId): array
    {
        $empty = [
            'company' => null,
            'membership' => null,
            'branches' => new Collection,
            'branch_assignments' => new Collection,
        ];

        if (! $companyId) {
            return $empty;
        }

        $membership = Membership::query()
            ->with(['company', 'branch'])
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership && ! $this->developerAccess->userIsDeveloper($user)) {
            return $empty;
        }

        $company = $membership?->company ?? Company::query()->whereKey($companyId)->where('status', 'active')->first();

        if (! $company) {
            return $empty;
        }

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        $branchAssignments = BranchAssignment::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->with('role')
            ->get();

        return [
            'company' => $company,
            'membership' => $membership,
            'branches' => $branches,
            'branch_assignments' => $branchAssignments,
        ];
    }
}

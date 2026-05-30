<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Organization\Services\DeveloperAccess;
use Illuminate\Support\Collection;

class ListUserCompanies
{
    public function __construct(private readonly DeveloperAccess $developerAccess) {}

    /**
     * Return active company entries visible to the authenticated user.
     *
     * @return Collection<int, Membership|array{company: Company, membership: null}>
     */
    public function execute(User $user): Collection
    {
        if ($this->developerAccess->userIsDeveloper($user)) {
            return Company::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn (Company $company): array => [
                    'company' => $company,
                    'membership' => null,
                ]);
        }

        return $user->memberships()
            ->with(['company', 'branch'])
            ->where('memberships.status', 'active')
            ->whereHas('company', fn ($query) => $query->where('companies.status', 'active'))
            ->join('companies', 'memberships.company_id', '=', 'companies.id')
            ->orderBy('companies.name')
            ->select('memberships.*')
            ->get();
    }
}

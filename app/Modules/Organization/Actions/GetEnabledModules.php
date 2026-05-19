<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Organization\Models\ModuleEntitlement;

class GetEnabledModules
{
    /**
     * Return module visibility for the current user and company context.
     *
     * @return array{enabled: list<string>, available: list<string>, company: Company|null}
     */
    public function execute(User $user, ?int $companyId): array
    {
        if (! $companyId || ! $this->hasActiveMembership($user, $companyId)) {
            return [
                'enabled' => [],
                'available' => [],
                'company' => null,
            ];
        }

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($companyId);

        try {
            $canManageEntitlements = $user->can('organization.manage-entitlements');
            $enabled = [];
            $available = [];

            $entitlements = ModuleEntitlement::query()
                ->where('company_id', $companyId)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('module')
                ->get();

            foreach ($entitlements as $entitlement) {
                if (! $this->canSeeModule($user, $entitlement->module, $canManageEntitlements)) {
                    continue;
                }

                if ($entitlement->is_enabled) {
                    $enabled[] = $entitlement->module;

                    continue;
                }

                $available[] = $entitlement->module;
            }

            return [
                'enabled' => $enabled,
                'available' => $available,
                'company' => Company::find($companyId),
            ];
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    private function hasActiveMembership(User $user, int $companyId): bool
    {
        return Membership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    private function canSeeModule(User $user, string $module, bool $canManageEntitlements): bool
    {
        return $canManageEntitlements || $user->can("{$module}.view");
    }
}

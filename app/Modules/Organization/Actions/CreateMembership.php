<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateMembership
{
    private const ADMIN_PERMISSIONS = [
        'organization.view',
        'organization.manage-branches',
        'organization.manage-members',
    ];

    /**
     * @param  array{user_id: int, branch_id?: int|null, role?: string}  $data
     */
    public function execute(Company $company, User $actor, array $data): Membership
    {
        /** @var User $member */
        $member = User::findOrFail($data['user_id']);

        $membership = Membership::create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'branch_id' => $data['branch_id'] ?? null,
            'role' => $data['role'] ?? 'member',
            'status' => 'active',
            'joined_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        if ($membership->role === 'admin') {
            $this->assignAdminRole($member, $company);
        }

        return $membership->load(['user', 'branch']);
    }

    private function assignAdminRole(User $user, Company $company): void
    {
        $previousTeamId = getPermissionsTeamId();

        try {
            foreach (self::ADMIN_PERMISSIONS as $permission) {
                Permission::findOrCreate($permission, 'api');
            }

            setPermissionsTeamId($company->id);

            $role = Role::findOrCreate('company-admin', 'api');
            $role->givePermissionTo(self::ADMIN_PERMISSIONS);

            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}

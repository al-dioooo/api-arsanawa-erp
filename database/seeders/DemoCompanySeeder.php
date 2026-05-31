<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Organization\Actions\CreateCompany;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Modules\Platform\Services\SettingsManager;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DemoCompanySeeder extends Seeder
{
    /**
     * Seed a usable demo company so a fresh install can be logged into immediately.
     */
    public function run(): void
    {
        $owner = User::query()->where('username', 'sekalori')->first();

        if ($owner === null) {
            return;
        }

        $company = Company::query()->where('slug', 'sekalori')->first();

        if ($company === null) {
            $created = app(CreateCompany::class)->execute($owner, [
                'name' => 'SEKALORI Catering',
                'slug' => 'sekalori',
                'legal_name' => 'PT Sekalori Rasa Nusantara',
                'primary_branch_name' => 'SEKALORI HQ',
            ]);
            $company = $created['company'];
        }

        $this->seedSekaloriSettings($company, $owner);

        $primaryBranch = Branch::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'MAIN',
            ],
            [
                'name' => 'SEKALORI HQ',
                'is_primary' => true,
                'status' => 'active',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );

        Membership::query()
            ->where('company_id', $company->id)
            ->where('user_id', '<>', $owner->id)
            ->where('role', 'owner')
            ->delete();

        Membership::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $owner->id,
            ],
            [
                'branch_id' => $primaryBranch->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );

        $this->assignOwnerRole($owner, $company);
    }

    private function seedSekaloriSettings(Company $company, User $owner): void
    {
        $settings = app(SettingsManager::class);

        $settings->set($company->id, 'pos', 'catering_only', true, null, $owner->id);
        $settings->set($company->id, 'inventory', 'hide_catering_restricted_features', true, null, $owner->id);
    }

    private function assignOwnerRole(User $user, Company $company): void
    {
        $previousTeamId = getPermissionsTeamId();

        try {
            $permissions = PermissionCatalog::keys();

            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'api');
            }

            setPermissionsTeamId($company->id);

            $role = Role::findOrCreate('company-owner', 'api');
            $role->givePermissionTo($permissions);

            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}

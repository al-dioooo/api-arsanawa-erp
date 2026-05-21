<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Membership;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateCompany
{
    /**
     * Create a company, its primary branch, and the first owner membership.
     *
     * @param  array{name: string, slug?: string, legal_name?: string|null, tax_identifier?: string|null, primary_branch_name?: string|null}  $data
     * @return array{company: Company, primaryBranch: Branch, membership: Membership}
     */
    public function execute(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $company = Company::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                'legal_name' => $data['legal_name'] ?? null,
                'tax_identifier' => $data['tax_identifier'] ?? null,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $primaryBranch = Branch::create([
                'company_id' => $company->id,
                'name' => $data['primary_branch_name'] ?? 'Main Branch',
                'code' => 'MAIN',
                'is_primary' => true,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $membership = Membership::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'branch_id' => $primaryBranch->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->assignOwnerRole($user, $company);

            return compact('company', 'primaryBranch', 'membership');
        });
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'company';
        $slug = $baseSlug;
        $suffix = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
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

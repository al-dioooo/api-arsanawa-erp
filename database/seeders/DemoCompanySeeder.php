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
        $settings->set($company->id, 'pos', 'catering_form_import', [
            'source_url' => 'https://docs.google.com/spreadsheets/d/1SXmxOwiaxiKpl9r-JmlKV-1-0xIhS0EPaZK7aCkQAEM/export?format=csv&gid=2117219189',
            'field_map' => [
                'timestamp' => 'Timestamp',
                'customer_name' => 'Nama Lengkap',
                'customer_phone' => 'Nomor WhatsApp',
                'delivery_address' => 'Alamat Pengiriman',
                'menu_type' => 'Jenis Menu',
                'fulfilment_time_window' => 'Batch Pengiriman',
                'payment_method' => 'Metode Pembayaran',
                'payment_reference' => 'Bukti Transfer',
                'notes' => 'Catatan',
            ],
            'menu_type_bundle_skus' => [
                'Indonesian Local' => 'SKL-BND-IDN',
                'Western' => 'SKL-BND-WST',
                'Japanese' => 'SKL-BND-JPN',
            ],
            'default_branch_code' => 'MAIN',
            'default_quantity' => 1,
            'fulfilment_date_rule' => 'timestamp_plus_one_day',
            'payment_method_map' => [
                'Transfer Bank' => 'transfer',
                'E-Wallet' => 'qris',
                'COD' => null,
            ],
            'default_import_register_code' => 'GFORM-IMPORT',
        ], null, $owner->id);
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

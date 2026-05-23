<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Pos\Models\Register;
use Illuminate\Database\Seeder;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'sekalori')->first();
        $branch = $company ? Branch::query()->where('company_id', $company->id)->orderBy('id')->first() : null;
        $userId = User::query()->first()?->id ?? 1;

        if ($company === null || $branch === null) {
            return;
        }

        $cashAccount = Account::query()
            ->forCompany($company->id)
            ->where('code', '1-1100')
            ->first();

        $cogsAccount = Account::query()
            ->forCompany($company->id)
            ->where('code', '5-1200')
            ->first();

        $inventoryAccount = Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => '1-1500'],
            [
                'name' => 'Inventory Asset',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'depth' => 0,
                'is_postable' => true,
                'currency_id' => 1,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );

        if ($cashAccount !== null) {
            Register::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'HQ-01'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'SEKALORI HQ Register',
                    'cash_account_id' => $cashAccount->id,
                    'is_active' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        if ($cogsAccount !== null) {
            AccountMapping::firstOrCreate(
                ['company_id' => $company->id, 'key' => 'cogs'],
                [
                    'account_id' => $cogsAccount->id,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        AccountMapping::firstOrCreate(
            ['company_id' => $company->id, 'key' => 'inventory_asset'],
            [
                'account_id' => $inventoryAccount->id,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }
}

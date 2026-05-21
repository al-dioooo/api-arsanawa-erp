<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Organization\Actions\CreateCompany;
use App\Modules\Organization\Models\Company;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    /**
     * Seed a usable demo company so a fresh install can be logged into immediately.
     */
    public function run(): void
    {
        $user = User::query()->where('username', 'aliceevr')->first();

        if ($user === null || Company::query()->where('slug', 'sekalori')->exists()) {
            return;
        }

        app(CreateCompany::class)->execute($user, [
            'name' => 'SEKALORI Catering',
            'slug' => 'sekalori',
            'legal_name' => 'PT Sekalori Rasa Nusantara',
            'primary_branch_name' => 'SEKALORI HQ',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CurrencySeeder::class);
        $this->call(PermissionSeeder::class);

        $alice = User::query()->firstOrNew(['username' => 'aliceevr']);
        $alice->forceFill([
            'name' => 'Alice Evergarden',
            'email' => 'hello@al.is-a.dev',
            'password' => Hash::make('aldio1234'),
            'is_developer' => true,
        ])->save();

        $sekaloriOwner = User::query()->firstOrNew(['username' => 'sekalori']);
        $sekaloriOwner->forceFill([
            'name' => 'SEKALORI Demo Owner',
            'email' => 'owner@sekalori.test',
            'password' => Hash::make('sekalori1234'),
            'is_developer' => false,
        ])->save();

        $this->call(DemoCompanySeeder::class);
        $this->call(InventoryDemoSeeder::class);
        $this->call(FinanceDemoSeeder::class);
        $this->call(PosDemoSeeder::class);
    }
}

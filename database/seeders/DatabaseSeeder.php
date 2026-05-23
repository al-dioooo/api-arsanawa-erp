<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        User::factory()->create([
            'name' => 'Alice Evergarden',
            'username' => 'aliceevr',
            'email' => 'hello@al.is-a.dev',
            'password' => bcrypt('aldio1234'),
        ]);

        $this->call(DemoCompanySeeder::class);
        $this->call(InventoryDemoSeeder::class);
        $this->call(FinanceDemoSeeder::class);
        $this->call(PosDemoSeeder::class);
    }
}

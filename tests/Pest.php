<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Shared Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user, a company they own, and return the auth context.
 * The owner holds every catalogued permission for that company.
 *
 * @return array{0: User, 1: string, 2: int, 3: int}
 *                                                   [owner, bearer token, company id, primary branch id]
 */
function inventoryActor(): array
{
    $owner = User::factory()->create();

    $token = test()->postJson('/api/v1/auth/login', [
        'login' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $company = test()->withToken($token)
        ->postJson('/api/v1/organization/companies', [
            'name' => 'Inventory Co '.uniqid(),
        ])
        ->json('data');

    return [$owner, $token, $company['company']['id'], $company['primary_branch']['id']];
}

function createCategory(string $token, int $companyId, string $name, ?int $parentId = null): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/categories', ['name' => $name, 'parent_id' => $parentId])
        ->json('data.category.id');
}

function createBrand(string $token, int $companyId, string $name): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/brands', ['name' => $name])
        ->json('data.brand.id');
}

function createUnit(string $token, int $companyId, string $code, ?string $name = null): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/units-of-measure', ['name' => $name ?? mb_strtoupper($code), 'code' => $code])
        ->json('data.unit.id');
}

/**
 * @param  array<string, mixed>  $data
 */
function createProduct(string $token, int $companyId, array $data): int
{
    return test()->withToken($token)
        ->withHeader('X-Company-Id', (string) $companyId)
        ->postJson('/api/v1/inventory/products', $data)
        ->json('data.product.id');
}

<?php

/*
|--------------------------------------------------------------------------
| Permission Catalog
|--------------------------------------------------------------------------
|
| The single source of truth for every permission the ERP exposes, grouped
| by module. `php artisan permission:sync` (and PermissionSeeder) creates
| these in the spatie `permissions` table. Companies build custom roles by
| selecting from these keys — see App\Support\PermissionCatalog.
|
*/

return [

    'organization' => [
        'label' => 'Organization',
        'permissions' => [
            ['key' => 'organization.view', 'label' => 'View organization'],
            ['key' => 'organization.manage-company', 'label' => 'Manage company'],
            ['key' => 'organization.manage-branches', 'label' => 'Manage branches'],
            ['key' => 'organization.manage-members', 'label' => 'Manage members'],
            ['key' => 'organization.manage-roles', 'label' => 'Manage roles'],
            ['key' => 'organization.manage-entitlements', 'label' => 'Manage module entitlements'],
        ],
    ],

    'identity' => [
        'label' => 'Identity',
        'permissions' => [
            ['key' => 'identity.view', 'label' => 'View users'],
        ],
    ],

    'platform' => [
        'label' => 'Platform',
        'permissions' => [
            ['key' => 'platform.manage-settings', 'label' => 'Manage settings'],
        ],
    ],

    'partners' => [
        'label' => 'Partners',
        'permissions' => [
            ['key' => 'partners.view', 'label' => 'View partners'],
            ['key' => 'partners.create', 'label' => 'Create partners'],
            ['key' => 'partners.update', 'label' => 'Update partners'],
            ['key' => 'partners.delete', 'label' => 'Delete partners'],
        ],
    ],

    'inventory' => [
        'label' => 'Inventory',
        'permissions' => [
            ['key' => 'inventory.view', 'label' => 'View inventory'],
            ['key' => 'inventory.manage-products', 'label' => 'Manage products & catalogue'],
            ['key' => 'inventory.manage-stock', 'label' => 'Manage stock'],
            ['key' => 'inventory.manage-pricing', 'label' => 'Manage pricing'],
            ['key' => 'inventory.manage-promotions', 'label' => 'Manage discounts & rewards'],
        ],
    ],

];

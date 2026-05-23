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

    'finance' => [
        'label' => 'Finance',
        'permissions' => [
            ['key' => 'finance.view', 'label' => 'View finance data'],
            ['key' => 'finance.manage-accounts', 'label' => 'Manage chart of accounts'],
            ['key' => 'finance.manage-journals', 'label' => 'Manage journal entries'],
            ['key' => 'finance.manage-receivables', 'label' => 'Manage accounts receivable'],
            ['key' => 'finance.manage-payables', 'label' => 'Manage accounts payable'],
            ['key' => 'finance.manage-payments', 'label' => 'Manage payments'],
            ['key' => 'finance.approve', 'label' => 'Approve financial documents'],
            ['key' => 'finance.manage-tax', 'label' => 'Manage tax returns'],
        ],
    ],

    'pos' => [
        'label' => 'POS',
        'permissions' => [
            ['key' => 'pos.view', 'label' => 'View POS data'],
            ['key' => 'pos.operate', 'label' => 'Operate registers and sales'],
            ['key' => 'pos.manage-registers', 'label' => 'Manage POS registers'],
            ['key' => 'pos.manage-orders', 'label' => 'Manage catering orders'],
            ['key' => 'pos.void-sales', 'label' => 'Void POS sales'],
            ['key' => 'pos.view-reports', 'label' => 'View POS reports'],
        ],
    ],

];

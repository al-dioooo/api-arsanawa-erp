<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\TaxRate;
use Illuminate\Database\Seeder;

class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        // This seeder populates default chart of accounts, tax rates,
        // account mappings, and accounting periods for SEKALORI Catering.

        $companyId = 1;
        $userId = User::first()?->id ?? 1;

        // 1. Chart of Accounts
        $accounts = [
            ['code' => '1-1100', 'name' => 'Cash & Bank', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1-1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1-1300', 'name' => 'VAT Input', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1-1400', 'name' => 'Vendor Prepayments', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2-1100', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2-1200', 'name' => 'VAT Output', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2-1300', 'name' => 'Withholding Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2-1400', 'name' => 'Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2-1500', 'name' => 'Customer Prepayments', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3-1100', 'name' => 'Retained Earnings', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '4-1100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5-1100', 'name' => 'Purchase Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5-1200', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        $createdAccounts = [];
        foreach ($accounts as $acc) {
            $createdAccounts[$acc['code']] = Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $acc['code']],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'normal_balance' => $acc['normal_balance'],
                    'depth' => 0,
                    'is_postable' => true,
                    'currency_id' => 1,
                    'is_active' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }

        // 2. Account Mappings
        $mappings = [
            'accounts_receivable' => '1-1200',
            'sales_revenue' => '4-1100',
            'vat_output' => '2-1200',
            'accounts_payable' => '2-1100',
            'purchase_expense' => '5-1100',
            'vat_input' => '1-1300',
            'withholding_tax_payable' => '2-1300',
            'tax_payable' => '2-1400',
            'customer_prepayments' => '2-1500',
            'vendor_prepayments' => '1-1400',
        ];

        foreach ($mappings as $key => $code) {
            $account = $createdAccounts[$code] ?? null;
            if ($account) {
                AccountMapping::firstOrCreate(
                    ['company_id' => $companyId, 'key' => $key],
                    [
                        'account_id' => $account->id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
            }
        }

        // 3. Tax Rates
        $taxRates = [
            ['name' => 'VAT 11%', 'type' => 'vat', 'rate' => 11.0000],
            ['name' => 'Withholding 2%', 'type' => 'withholding', 'rate' => 2.0000],
        ];

        foreach ($taxRates as $tr) {
            TaxRate::firstOrCreate(
                ['company_id' => $companyId, 'name' => $tr['name']],
                [
                    'type' => $tr['type'],
                    'rate' => $tr['rate'],
                    'is_active' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }

        // 4. Accounting Periods
        $periods = [
            ['name' => 'May 2026', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31'],
            ['name' => 'June 2026', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30'],
        ];

        foreach ($periods as $p) {
            AccountingPeriod::firstOrCreate(
                ['company_id' => $companyId, 'name' => $p['name']],
                [
                    'start_date' => $p['start_date'],
                    'end_date' => $p['end_date'],
                    'status' => 'open',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }
    }
}

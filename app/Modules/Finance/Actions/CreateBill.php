<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillLine;
use App\Modules\Finance\Services\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBill
{
    protected TaxCalculator $calculator;

    public function __construct(TaxCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Create a draft vendor bill.
     *
     * @param  array{
     *     partner_id: int,
     *     branch_id?: int|null,
     *     bill_number?: string|null,
     *     bill_date: string,
     *     due_date: string,
     *     currency_id?: int|null,
     *     exchange_rate?: float|numeric|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         product_variant_id?: int|null,
     *         expense_account_id?: int|null,
     *         description: string,
     *         quantity: float|numeric,
     *         unit_price: float|numeric,
     *         discount?: float|numeric|null,
     *         tax_rate_id?: int|null
     *     }>
     * }  $data
     */
    public function execute(int $companyId, User $user, array $data): Bill
    {
        $calculations = $this->calculator->calculate($data['lines']);

        return DB::transaction(function () use ($companyId, $user, $data, $calculations): Bill {
            $billNumber = $data['bill_number'] ?? ('BILL-'.date('Ymd').'-'.strtoupper(Str::random(6)));

            $bill = Bill::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'bill_number' => $billNumber,
                'partner_id' => $data['partner_id'],
                'currency_id' => $data['currency_id'] ?? 1, // Default IDR
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'],
                'status' => 'draft',
                'subtotal' => $calculations['subtotal'],
                'discount_total' => $calculations['discount_total'],
                'tax_total' => $calculations['tax_total'],
                'withholding_total' => $calculations['withholding_total'],
                'total' => $calculations['total'],
                'amount_paid' => '0.0000',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['lines'] as $index => $line) {
                $calcLine = $calculations['lines'][$index];

                BillLine::create([
                    'bill_id' => $bill->id,
                    'description' => $line['description'],
                    'product_variant_id' => $line['product_variant_id'] ?? null,
                    'expense_account_id' => $line['expense_account_id'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'] ?? 0,
                    'line_subtotal' => $calcLine['line_subtotal'],
                    'tax_amount' => $calcLine['tax_amount'],
                    'withholding_amount' => $calcLine['withholding_amount'],
                    'line_total' => $calcLine['line_total'],
                    'tax_rate_id' => $line['tax_rate_id'] ?? null,
                ]);
            }

            return $bill->load('lines');
        });
    }
}

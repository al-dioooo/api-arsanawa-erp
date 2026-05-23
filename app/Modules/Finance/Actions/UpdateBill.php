<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillLine;
use App\Modules\Finance\Services\ApprovalService;
use App\Modules\Finance\Services\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBill
{
    protected TaxCalculator $calculator;

    public function __construct(TaxCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Update a draft vendor bill.
     *
     * @param  array{
     *     partner_id?: int,
     *     branch_id?: int|null,
     *     bill_number?: string,
     *     bill_date?: string,
     *     due_date?: string,
     *     currency_id?: int,
     *     exchange_rate?: float|numeric,
     *     notes?: string|null,
     *     lines?: array<int, array{
     *         product_variant_id?: int|null,
     *         expense_account_id?: int|null,
     *         description: string,
     *         quantity: float|numeric,
     *         unit_price: float|numeric,
     *         discount?: float|numeric|null,
     *         tax_rate_id?: int|null
     *     }>
     * }  $data
     *
     * @throws ValidationException
     */
    public function execute(Bill $bill, User $user, array $data): Bill
    {
        if ($bill->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft bills can be updated.')],
            ]);
        }

        return DB::transaction(function () use ($bill, $user, $data): Bill {
            // If lines are provided, recalculate and replace them
            if (isset($data['lines'])) {
                $calculations = $this->calculator->calculate($data['lines']);

                // Delete old lines
                $bill->lines()->delete();

                // Create new lines
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

                // Update bill totals
                $bill->fill([
                    'subtotal' => $calculations['subtotal'],
                    'discount_total' => $calculations['discount_total'],
                    'tax_total' => $calculations['tax_total'],
                    'withholding_total' => $calculations['withholding_total'],
                    'total' => $calculations['total'],
                ]);
            }

            // Update other attributes
            $fillData = [];
            foreach (['partner_id', 'branch_id', 'bill_number', 'bill_date', 'due_date', 'currency_id', 'exchange_rate', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fillData[$field] = $data[$field];
                }
            }
            $bill->fill($fillData);

            $bill->updated_by = $user->id;
            $bill->save();

            app(ApprovalService::class)->reset($bill);

            return $bill->load('lines');
        });
    }
}

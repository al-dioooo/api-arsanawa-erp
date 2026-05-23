<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Services\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoice
{
    protected TaxCalculator $calculator;

    public function __construct(TaxCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Update a draft customer invoice.
     *
     * @param  array{
     *     partner_id?: int,
     *     branch_id?: int|null,
     *     invoice_number?: string,
     *     invoice_date?: string,
     *     due_date?: string,
     *     currency_id?: int,
     *     exchange_rate?: float|numeric,
     *     notes?: string|null,
     *     lines?: array<int, array{
     *         product_variant_id?: int|null,
     *         revenue_account_id?: int|null,
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
    public function execute(Invoice $invoice, User $user, array $data): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft invoices can be updated.')],
            ]);
        }

        return DB::transaction(function () use ($invoice, $user, $data): Invoice {
            // If lines are provided, recalculate and replace them
            if (isset($data['lines'])) {
                $calculations = $this->calculator->calculate($data['lines']);

                // Delete old lines
                $invoice->lines()->delete();

                // Create new lines
                foreach ($data['lines'] as $index => $line) {
                    $calcLine = $calculations['lines'][$index];

                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'description' => $line['description'],
                        'product_variant_id' => $line['product_variant_id'] ?? null,
                        'revenue_account_id' => $line['revenue_account_id'] ?? null,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount' => $line['discount'] ?? 0,
                        'line_subtotal' => $calcLine['line_subtotal'],
                        'tax_amount' => $calcLine['tax_amount'],
                        'line_total' => $calcLine['line_total'],
                        'tax_rate_id' => $line['tax_rate_id'] ?? null,
                    ]);
                }

                // Update invoice totals
                $invoice->fill([
                    'subtotal' => $calculations['subtotal'],
                    'discount_total' => $calculations['discount_total'],
                    'tax_total' => $calculations['tax_total'],
                    'total' => $calculations['total'],
                ]);
            }

            // Update other attributes
            $fillData = [];
            foreach (['partner_id', 'branch_id', 'invoice_number', 'invoice_date', 'due_date', 'currency_id', 'exchange_rate', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fillData[$field] = $data[$field];
                }
            }
            $invoice->fill($fillData);

            $invoice->updated_by = $user->id;
            $invoice->save();

            return $invoice->load('lines');
        });
    }
}

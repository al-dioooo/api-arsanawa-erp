<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\TaxReturn;
use App\Modules\Finance\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeTaxReturn
{
    protected PostingService $postingService;

    public function __construct(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Finalize a tax return and post a settlement journal entry.
     *
     * @throws ValidationException
     */
    public function execute(TaxReturn $taxReturn, User $user): TaxReturn
    {
        if ($taxReturn->status !== 'draft') {
            throw ValidationException::withMessages([
                'tax_return' => [__('Only draft tax returns can be finalized.')],
            ]);
        }

        // 1. Resolve open accounting period covering period_end
        $period = AccountingPeriod::query()
            ->forCompany($taxReturn->company_id)
            ->where('start_date', '<=', $taxReturn->period_end)
            ->where('end_date', '>=', $taxReturn->period_end)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'period_end' => [__('No accounting period found for the tax return end date.')],
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'period_end' => [__('The accounting period for the tax return end date is closed.')],
            ]);
        }

        // Check if it is a nil return
        $isNil = bccomp((string) $taxReturn->total_output, '0.0000', 4) === 0 &&
                 bccomp((string) $taxReturn->total_input, '0.0000', 4) === 0 &&
                 bccomp((string) $taxReturn->total_payable, '0.0000', 4) === 0;

        $vatOutputMapping = null;
        $vatInputMapping = null;
        $taxPayableMapping = null;
        $withholdingMapping = null;

        // 2. Resolve account mappings (only if not nil)
        if (! $isNil) {
            if ($taxReturn->tax_type === 'vat') {
                $vatOutputMapping = AccountMapping::query()
                    ->forCompany($taxReturn->company_id)
                    ->where('key', 'vat_output')
                    ->first();
                $vatInputMapping = AccountMapping::query()
                    ->forCompany($taxReturn->company_id)
                    ->where('key', 'vat_input')
                    ->first();
                $taxPayableMapping = AccountMapping::query()
                    ->forCompany($taxReturn->company_id)
                    ->where('key', 'tax_payable')
                    ->first();

                if (! $vatOutputMapping || ! $vatInputMapping || ! $taxPayableMapping) {
                    throw ValidationException::withMessages([
                        'account_mappings' => [__('Required tax account mappings (vat_output, vat_input, tax_payable) are missing.')],
                    ]);
                }
            } elseif ($taxReturn->tax_type === 'withholding') {
                $withholdingMapping = AccountMapping::query()
                    ->forCompany($taxReturn->company_id)
                    ->where('key', 'withholding_tax_payable')
                    ->first();
                $taxPayableMapping = AccountMapping::query()
                    ->forCompany($taxReturn->company_id)
                    ->where('key', 'tax_payable')
                    ->first();

                if (! $withholdingMapping || ! $taxPayableMapping) {
                    throw ValidationException::withMessages([
                        'account_mappings' => [__('Required withholding account mappings (withholding_tax_payable, tax_payable) are missing.')],
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($taxReturn, $user, $period, $isNil, $vatOutputMapping, $vatInputMapping, $taxPayableMapping, $withholdingMapping): TaxReturn {
            $journalEntryId = null;

            if (! $isNil) {
                $entryNumber = 'JE-TAX-'.strtoupper($taxReturn->tax_type).'-'.$taxReturn->id;

                $entry = JournalEntry::create([
                    'company_id' => $taxReturn->company_id,
                    'branch_id' => null,
                    'entry_number' => $entryNumber,
                    'entry_date' => $taxReturn->period_end,
                    'accounting_period_id' => $period->id,
                    'description' => 'System posted '.strtoupper($taxReturn->tax_type).' return settlement for period '.$taxReturn->period_start->format('Y-m-d').' to '.$taxReturn->period_end->format('Y-m-d'),
                    'reference_type' => 'TaxReturn',
                    'reference_id' => $taxReturn->id,
                    'currency_id' => 1, // Default IDR
                    'exchange_rate' => 1.0,
                    'status' => 'draft',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                if ($taxReturn->tax_type === 'vat') {
                    // Clear VAT Output: Debit vat_output
                    if (bccomp((string) $taxReturn->total_output, '0.0000', 4) > 0) {
                        JournalLine::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $vatOutputMapping->account_id,
                            'description' => 'Clear VAT Output',
                            'debit' => $taxReturn->total_output,
                            'credit' => 0,
                            'foreign_debit' => $taxReturn->total_output,
                            'foreign_credit' => 0,
                        ]);
                    }

                    // Clear VAT Input: Credit vat_input
                    if (bccomp((string) $taxReturn->total_input, '0.0000', 4) > 0) {
                        JournalLine::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $vatInputMapping->account_id,
                            'description' => 'Clear VAT Input',
                            'debit' => 0,
                            'credit' => $taxReturn->total_input,
                            'foreign_debit' => 0,
                            'foreign_credit' => $taxReturn->total_input,
                        ]);
                    }

                    // Net VAT Payable/Refund to tax_payable
                    $payableComp = bccomp((string) $taxReturn->total_payable, '0.0000', 4);
                    if ($payableComp > 0) {
                        // Credit tax_payable
                        JournalLine::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $taxPayableMapping->account_id,
                            'description' => 'VAT Net Payable',
                            'debit' => 0,
                            'credit' => $taxReturn->total_payable,
                            'foreign_debit' => 0,
                            'foreign_credit' => $taxReturn->total_payable,
                        ]);
                    } elseif ($payableComp < 0) {
                        // Debit tax_payable (Refund/Receivable)
                        $absPayable = bcmul((string) $taxReturn->total_payable, '-1', 4);
                        JournalLine::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $taxPayableMapping->account_id,
                            'description' => 'VAT Net Refund',
                            'debit' => $absPayable,
                            'credit' => 0,
                            'foreign_debit' => $absPayable,
                            'foreign_credit' => 0,
                        ]);
                    }
                } elseif ($taxReturn->tax_type === 'withholding') {
                    // Clear Withholding Tax Payable: Debit withholding_tax_payable
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $withholdingMapping->account_id,
                        'description' => 'Clear Withholding Tax Payable',
                        'debit' => $taxReturn->total_payable,
                        'credit' => 0,
                        'foreign_debit' => $taxReturn->total_payable,
                        'foreign_credit' => 0,
                    ]);

                    // Net Payable to tax_payable: Credit tax_payable
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $taxPayableMapping->account_id,
                        'description' => 'Withholding Net Payable',
                        'debit' => 0,
                        'credit' => $taxReturn->total_payable,
                        'foreign_debit' => 0,
                        'foreign_credit' => $taxReturn->total_payable,
                    ]);
                }

                $this->postingService->post($entry, $user);
                $journalEntryId = $entry->id;
            }

            $taxReturn->update([
                'status' => 'finalized',
                'journal_entry_id' => $journalEntryId,
                'updated_by' => $user->id,
            ]);

            return $taxReturn;
        });
    }
}

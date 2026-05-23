<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Services\PostingService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteSale
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly PostingService $postingService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user): Sale
    {
        if (! in_array($sale->status, ['draft', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'sale' => [__('Only draft or confirmed sales can be completed.')],
            ]);
        }

        if ($sale->type === 'counter' && bccomp((string) $sale->amount_paid, (string) $sale->total, 4) < 0) {
            throw ValidationException::withMessages([
                'amount_paid' => [__('Counter sales must be fully paid before completion.')],
            ]);
        }

        if ($sale->type === 'catering' && $sale->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'sale' => [__('Catering orders must be confirmed before completion.')],
            ]);
        }

        return DB::transaction(function () use ($sale, $user): Sale {
            $sale->load(['lines', 'register']);
            $period = $this->periodFor($sale);

            foreach ($sale->lines as $line) {
                $this->stockService->recordIssue([
                    'company_id' => $sale->company_id,
                    'branch_id' => $sale->branch_id,
                    'product_variant_id' => $line->product_variant_id,
                    'quantity' => $line->quantity,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => 'POS sale '.$sale->sale_number,
                    'created_by' => $user->id,
                ]);
            }

            $revenueEntry = $this->createRevenueEntry($sale, $period, $user);
            $this->postingService->post($revenueEntry, $user);

            $cogsAmount = $this->cogsAmount($sale);
            $cogsEntry = $this->createCogsEntry($sale, $period, $user, $cogsAmount);
            $this->postingService->post($cogsEntry, $user);

            $sale->update([
                'status' => 'completed',
                'revenue_journal_entry_id' => $revenueEntry->id,
                'cogs_journal_entry_id' => $cogsEntry->id,
                'completed_at' => now(),
                'updated_by' => $user->id,
            ]);

            return $sale->load(['lines', 'payments', 'promotions', 'register']);
        });
    }

    /**
     * @throws ValidationException
     */
    private function periodFor(Sale $sale): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->forCompany($sale->company_id)
            ->where('start_date', '<=', $sale->order_date)
            ->where('end_date', '>=', $sale->order_date)
            ->first();

        if (! $period || $period->status !== 'open') {
            throw ValidationException::withMessages([
                'order_date' => [__('No open accounting period found for the sale date.')],
            ]);
        }

        return $period;
    }

    /**
     * @throws ValidationException
     */
    private function mappedAccount(int $companyId, string $key): int
    {
        $mapping = AccountMapping::query()
            ->forCompany($companyId)
            ->where('key', $key)
            ->first();

        if (! $mapping) {
            throw ValidationException::withMessages([
                $key => [__('Account mapping :key is missing for this company.', ['key' => $key])],
            ]);
        }

        return $mapping->account_id;
    }

    /**
     * @throws ValidationException
     */
    private function createRevenueEntry(Sale $sale, AccountingPeriod $period, User $user): JournalEntry
    {
        $cashAmount = (string) $sale->amount_paid;
        $receivableAmount = bcsub((string) $sale->total, $cashAmount, 4);
        $revenueAmount = bcsub((string) $sale->total, (string) $sale->tax_total, 4);
        $cashAccountId = $sale->register?->cash_account_id;

        if (bccomp($cashAmount, '0.0000', 4) > 0 && ! $cashAccountId) {
            throw ValidationException::withMessages([
                'register' => [__('The register does not have a cash account configured.')],
            ]);
        }

        $entry = JournalEntry::create([
            'company_id' => $sale->company_id,
            'branch_id' => $sale->branch_id,
            'entry_number' => 'JE-POS-REV-'.$sale->sale_number,
            'entry_date' => $sale->order_date,
            'accounting_period_id' => $period->id,
            'description' => 'System posted POS sale #'.$sale->sale_number,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'currency_id' => $sale->currency_id,
            'exchange_rate' => $sale->exchange_rate,
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if (bccomp($cashAmount, '0.0000', 4) > 0) {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $cashAccountId,
                'description' => 'Cash received for POS sale #'.$sale->sale_number,
                'debit' => $cashAmount,
                'credit' => 0,
                'foreign_debit' => $cashAmount,
                'foreign_credit' => 0,
            ]);
        }

        if (bccomp($receivableAmount, '0.0000', 4) > 0) {
            $arAccountId = $this->mappedAccount($sale->company_id, 'accounts_receivable');
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $arAccountId,
                'description' => 'Receivable for POS sale #'.$sale->sale_number,
                'debit' => $receivableAmount,
                'credit' => 0,
                'foreign_debit' => $receivableAmount,
                'foreign_credit' => 0,
            ]);
        }

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->mappedAccount($sale->company_id, 'sales_revenue'),
            'description' => 'Revenue for POS sale #'.$sale->sale_number,
            'debit' => 0,
            'credit' => $revenueAmount,
            'foreign_debit' => 0,
            'foreign_credit' => $revenueAmount,
        ]);

        if (bccomp((string) $sale->tax_total, '0.0000', 4) > 0) {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->mappedAccount($sale->company_id, 'vat_output'),
                'description' => 'VAT Output for POS sale #'.$sale->sale_number,
                'debit' => 0,
                'credit' => $sale->tax_total,
                'foreign_debit' => 0,
                'foreign_credit' => $sale->tax_total,
            ]);
        }

        return $entry;
    }

    private function cogsAmount(Sale $sale): string
    {
        return StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('type', 'issue')
            ->get()
            ->reduce(static function (string $carry, StockMovement $movement): string {
                $quantity = ltrim((string) $movement->quantity, '-');

                return bcadd($carry, bcmul($quantity, (string) $movement->unit_cost, 4), 4);
            }, '0.0000');
    }

    private function createCogsEntry(Sale $sale, AccountingPeriod $period, User $user, string $cogsAmount): JournalEntry
    {
        $entry = JournalEntry::create([
            'company_id' => $sale->company_id,
            'branch_id' => $sale->branch_id,
            'entry_number' => 'JE-POS-COGS-'.$sale->sale_number,
            'entry_date' => $sale->order_date,
            'accounting_period_id' => $period->id,
            'description' => 'System posted COGS for POS sale #'.$sale->sale_number,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'currency_id' => $sale->currency_id,
            'exchange_rate' => $sale->exchange_rate,
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->mappedAccount($sale->company_id, 'cogs'),
            'description' => 'COGS for POS sale #'.$sale->sale_number,
            'debit' => $cogsAmount,
            'credit' => 0,
            'foreign_debit' => $cogsAmount,
            'foreign_credit' => 0,
        ]);

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->mappedAccount($sale->company_id, 'inventory_asset'),
            'description' => 'Inventory relieved for POS sale #'.$sale->sale_number,
            'debit' => 0,
            'credit' => $cogsAmount,
            'foreign_debit' => 0,
            'foreign_credit' => $cogsAmount,
        ]);

        return $entry;
    }
}

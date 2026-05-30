<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\ClosePeriod;
use App\Modules\Finance\Actions\CreateAccount;
use App\Modules\Finance\Actions\CreateApprovalMatrix;
use App\Modules\Finance\Actions\CreateBill;
use App\Modules\Finance\Actions\CreateInvoice;
use App\Modules\Finance\Actions\CreateJournalEntry;
use App\Modules\Finance\Actions\CreatePayment;
use App\Modules\Finance\Actions\CreatePeriod;
use App\Modules\Finance\Actions\CreateTaxRate;
use App\Modules\Finance\Actions\DeleteAccount;
use App\Modules\Finance\Actions\DeleteApprovalMatrix;
use App\Modules\Finance\Actions\DeleteTaxRate;
use App\Modules\Finance\Actions\DeleteTaxReturn;
use App\Modules\Finance\Actions\FinalizeTaxReturn;
use App\Modules\Finance\Actions\GetFinanceDashboardSummary;
use App\Modules\Finance\Actions\GenerateTaxReturn;
use App\Modules\Finance\Actions\GetAccountLedger;
use App\Modules\Finance\Actions\GetTaxReturn;
use App\Modules\Finance\Actions\GetTrialBalance;
use App\Modules\Finance\Actions\ListAccountMappings;
use App\Modules\Finance\Actions\ListAccounts;
use App\Modules\Finance\Actions\ListApprovalMatrices;
use App\Modules\Finance\Actions\ListApprovalRequests;
use App\Modules\Finance\Actions\ListBills;
use App\Modules\Finance\Actions\ListInvoices;
use App\Modules\Finance\Actions\ListJournalEntries;
use App\Modules\Finance\Actions\ListPayments;
use App\Modules\Finance\Actions\ListPeriods;
use App\Modules\Finance\Actions\ListTaxRates;
use App\Modules\Finance\Actions\ListTaxReturns;
use App\Modules\Finance\Actions\PostBill;
use App\Modules\Finance\Actions\PostInvoice;
use App\Modules\Finance\Actions\PostJournalEntry;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Actions\ReopenPeriod;
use App\Modules\Finance\Actions\UpdateAccount;
use App\Modules\Finance\Actions\UpdateApprovalMatrix;
use App\Modules\Finance\Actions\UpdateBill;
use App\Modules\Finance\Actions\UpdateInvoice;
use App\Modules\Finance\Actions\UpdatePayment;
use App\Modules\Finance\Actions\UpdateTaxRate;
use App\Modules\Finance\Actions\UpsertAccountMappings;
use App\Modules\Finance\Actions\VoidBill;
use App\Modules\Finance\Actions\VoidInvoice;
use App\Modules\Finance\Actions\VoidJournalEntry;
use App\Modules\Finance\Actions\VoidPayment;
use App\Modules\Finance\Http\Requests\ActApprovalRequest;
use App\Modules\Finance\Http\Requests\ClosePeriodRequest;
use App\Modules\Finance\Http\Requests\CreateAccountRequest;
use App\Modules\Finance\Http\Requests\CreateApprovalMatrixRequest;
use App\Modules\Finance\Http\Requests\CreateBillRequest;
use App\Modules\Finance\Http\Requests\CreateInvoiceRequest;
use App\Modules\Finance\Http\Requests\CreateJournalEntryRequest;
use App\Modules\Finance\Http\Requests\CreatePaymentRequest;
use App\Modules\Finance\Http\Requests\CreatePeriodRequest;
use App\Modules\Finance\Http\Requests\CreateTaxRateRequest;
use App\Modules\Finance\Http\Requests\DeleteAccountRequest;
use App\Modules\Finance\Http\Requests\DeleteTaxRateRequest;
use App\Modules\Finance\Http\Requests\DeleteTaxReturnRequest;
use App\Modules\Finance\Http\Requests\FinalizeTaxReturnRequest;
use App\Modules\Finance\Http\Requests\GenerateTaxReturnRequest;
use App\Modules\Finance\Http\Requests\GetAccountLedgerRequest;
use App\Modules\Finance\Http\Requests\GetTrialBalanceRequest;
use App\Modules\Finance\Http\Requests\ListAccountMappingsRequest;
use App\Modules\Finance\Http\Requests\ListAccountsRequest;
use App\Modules\Finance\Http\Requests\ListApprovalMatricesRequest;
use App\Modules\Finance\Http\Requests\ListApprovalRequestsRequest;
use App\Modules\Finance\Http\Requests\ListBillsRequest;
use App\Modules\Finance\Http\Requests\ListInvoicesRequest;
use App\Modules\Finance\Http\Requests\ListJournalEntriesRequest;
use App\Modules\Finance\Http\Requests\ListPaymentsRequest;
use App\Modules\Finance\Http\Requests\ListPeriodsRequest;
use App\Modules\Finance\Http\Requests\ListTaxRatesRequest;
use App\Modules\Finance\Http\Requests\ListTaxReturnsRequest;
use App\Modules\Finance\Http\Requests\PostBillRequest;
use App\Modules\Finance\Http\Requests\PostInvoiceRequest;
use App\Modules\Finance\Http\Requests\PostJournalEntryRequest;
use App\Modules\Finance\Http\Requests\PostPaymentRequest;
use App\Modules\Finance\Http\Requests\ShowFinanceDashboardRequest;
use App\Modules\Finance\Http\Requests\SubmitApprovalRequest;
use App\Modules\Finance\Http\Requests\UpdateAccountRequest;
use App\Modules\Finance\Http\Requests\UpdateApprovalMatrixRequest;
use App\Modules\Finance\Http\Requests\UpdateBillRequest;
use App\Modules\Finance\Http\Requests\UpdateInvoiceRequest;
use App\Modules\Finance\Http\Requests\UpdatePaymentRequest;
use App\Modules\Finance\Http\Requests\UpdateTaxRateRequest;
use App\Modules\Finance\Http\Requests\UpsertAccountMappingsRequest;
use App\Modules\Finance\Http\Requests\VoidBillRequest;
use App\Modules\Finance\Http\Requests\VoidInvoiceRequest;
use App\Modules\Finance\Http\Requests\VoidJournalEntryRequest;
use App\Modules\Finance\Http\Requests\VoidPaymentRequest;
use App\Modules\Finance\Http\Resources\AccountMappingResource;
use App\Modules\Finance\Http\Resources\AccountResource;
use App\Modules\Finance\Http\Resources\ApprovalMatrixResource;
use App\Modules\Finance\Http\Resources\ApprovalRequestResource;
use App\Modules\Finance\Http\Resources\BillResource;
use App\Modules\Finance\Http\Resources\InvoiceResource;
use App\Modules\Finance\Http\Resources\JournalEntryResource;
use App\Modules\Finance\Http\Resources\PaymentResource;
use App\Modules\Finance\Http\Resources\PeriodResource;
use App\Modules\Finance\Http\Resources\TaxRateResource;
use App\Modules\Finance\Http\Resources\TaxReturnResource;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountingPeriod;
use App\Modules\Finance\Models\ApprovalMatrix;
use App\Modules\Finance\Models\ApprovalRequest;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\TaxRate;
use App\Modules\Finance\Models\TaxReturn;
use App\Modules\Finance\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function dashboard(ShowFinanceDashboardRequest $request, GetFinanceDashboardSummary $action): JsonResponse
    {
        return $this->success(
            $action->execute((int) $request->attributes->get('active_company_id')),
            __('Finance dashboard retrieved.'),
        );
    }

    // --- Chart of Accounts ------------------------------------------------

    public function accounts(ListAccountsRequest $request, ListAccounts $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['accounts' => AccountResource::collection($action->execute($companyId))],
            __('Accounts retrieved.'),
        );
    }

    public function storeAccount(CreateAccountRequest $request, CreateAccount $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $account = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['account' => new AccountResource($account)],
            __('Account created.'),
            201,
        );
    }

    public function showAccount(ListAccountsRequest $request, int $account): JsonResponse
    {
        return $this->success(
            ['account' => new AccountResource($this->resolveAccount($request, $account))],
            __('Account retrieved.'),
        );
    }

    public function updateAccount(UpdateAccountRequest $request, UpdateAccount $action, int $account): JsonResponse
    {
        $resolved = $this->resolveAccount($request, $account);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['account' => new AccountResource($updated)],
            __('Account updated.'),
        );
    }

    public function destroyAccount(DeleteAccountRequest $request, DeleteAccount $action, int $account): JsonResponse
    {
        $action->execute($this->resolveAccount($request, $account));

        return $this->success(null, __('Account deleted.'));
    }

    // --- Accounting Periods -----------------------------------------------

    public function periods(ListPeriodsRequest $request, ListPeriods $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['periods' => PeriodResource::collection($action->execute($companyId))],
            __('Periods retrieved.'),
        );
    }

    public function storePeriod(CreatePeriodRequest $request, CreatePeriod $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $period = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['period' => new PeriodResource($period)],
            __('Period created.'),
            201,
        );
    }

    public function closePeriod(ClosePeriodRequest $request, ClosePeriod $action, int $period): JsonResponse
    {
        $resolved = $this->resolvePeriod($request, $period);
        $updated = $action->execute($resolved, $request->user());

        return $this->success(
            ['period' => new PeriodResource($updated)],
            __('Period closed.'),
        );
    }

    public function reopenPeriod(ClosePeriodRequest $request, ReopenPeriod $action, int $period): JsonResponse
    {
        $resolved = $this->resolvePeriod($request, $period);
        $updated = $action->execute($resolved, $request->user());

        return $this->success(
            ['period' => new PeriodResource($updated)],
            __('Period reopened.'),
        );
    }

    // --- Tax Rates --------------------------------------------------------

    public function taxRates(ListTaxRatesRequest $request, ListTaxRates $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['tax_rates' => TaxRateResource::collection($action->execute($companyId))],
            __('Tax rates retrieved.'),
        );
    }

    public function storeTaxRate(CreateTaxRateRequest $request, CreateTaxRate $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $taxRate = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['tax_rate' => new TaxRateResource($taxRate)],
            __('Tax rate created.'),
            201,
        );
    }

    public function showTaxRate(ListTaxRatesRequest $request, int $taxRate): JsonResponse
    {
        return $this->success(
            ['tax_rate' => new TaxRateResource($this->resolveTaxRate($request, $taxRate))],
            __('Tax rate retrieved.'),
        );
    }

    public function updateTaxRate(UpdateTaxRateRequest $request, UpdateTaxRate $action, int $taxRate): JsonResponse
    {
        $resolved = $this->resolveTaxRate($request, $taxRate);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['tax_rate' => new TaxRateResource($updated)],
            __('Tax rate updated.'),
        );
    }

    public function destroyTaxRate(DeleteTaxRateRequest $request, DeleteTaxRate $action, int $taxRate): JsonResponse
    {
        $action->execute($this->resolveTaxRate($request, $taxRate));

        return $this->success(null, __('Tax rate deleted.'));
    }

    // --- Account Mappings -------------------------------------------------

    public function accountMappings(ListAccountMappingsRequest $request, ListAccountMappings $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['account_mappings' => AccountMappingResource::collection($action->execute($companyId))],
            __('Account mappings retrieved.'),
        );
    }

    public function upsertAccountMappings(UpsertAccountMappingsRequest $request, UpsertAccountMappings $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $mappings = $action->execute($companyId, $request->user(), $request->validated('mappings'));

        return $this->success(
            ['account_mappings' => AccountMappingResource::collection($mappings)],
            __('Account mappings updated.'),
        );
    }

    // --- Resolution helpers ----------------------------------------------

    private function resolveAccount(Request $request, int $id): Account
    {
        return Account::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolvePeriod(Request $request, int $id): AccountingPeriod
    {
        return AccountingPeriod::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveTaxRate(Request $request, int $id): TaxRate
    {
        return TaxRate::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    // --- Journal Entries --------------------------------------------------

    public function journalEntries(ListJournalEntriesRequest $request, ListJournalEntries $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $entries = $action->execute($companyId, $request->only(['status', 'per_page']));

        return $this->success(
            [
                'journal_entries' => JournalEntryResource::collection($entries->items()),
                'pagination' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                ],
            ],
            __('Journal entries retrieved.'),
        );
    }

    public function storeJournalEntry(CreateJournalEntryRequest $request, CreateJournalEntry $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $entry = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['journal_entry' => new JournalEntryResource($entry)],
            __('Journal entry created.'),
            201,
        );
    }

    public function showJournalEntry(ListJournalEntriesRequest $request, int $id): JsonResponse
    {
        $entry = $this->resolveJournalEntry($request, $id);

        return $this->success(
            ['journal_entry' => new JournalEntryResource($entry->load(['lines.account', 'period']))],
            __('Journal entry retrieved.'),
        );
    }

    public function postJournalEntry(PostJournalEntryRequest $request, PostJournalEntry $action, int $id): JsonResponse
    {
        $entry = $this->resolveJournalEntry($request, $id);
        $posted = $action->execute($entry, $request->user());

        return $this->success(
            ['journal_entry' => new JournalEntryResource($posted)],
            __('Journal entry posted.'),
        );
    }

    public function voidJournalEntry(VoidJournalEntryRequest $request, VoidJournalEntry $action, int $id): JsonResponse
    {
        $entry = $this->resolveJournalEntry($request, $id);
        $voided = $action->execute($entry, $request->user());

        return $this->success(
            ['journal_entry' => new JournalEntryResource($voided)],
            __('Journal entry voided.'),
        );
    }

    // --- Reports ---------------------------------------------------------

    public function trialBalance(GetTrialBalanceRequest $request, GetTrialBalance $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $periodId = (int) $request->input('accounting_period_id');
        $trialBalance = $action->execute($companyId, $periodId);

        return $this->success(
            ['trial_balance' => $trialBalance],
            __('Trial balance generated.'),
        );
    }

    public function accountLedger(GetAccountLedgerRequest $request, GetAccountLedger $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $accountId = (int) $request->input('account_id');
        $periodId = (int) $request->input('accounting_period_id');
        $report = $action->execute($companyId, $accountId, $periodId);

        return $this->success(
            $report,
            __('Account ledger generated.'),
        );
    }

    // --- Invoices ---------------------------------------------------------

    public function invoices(ListInvoicesRequest $request, ListInvoices $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $invoices = $action->execute($companyId, $request->only(['partner_id', 'status', 'per_page']));

        return $this->success(
            [
                'invoices' => InvoiceResource::collection($invoices->items()),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
            ],
            __('Invoices retrieved.'),
        );
    }

    public function storeInvoice(CreateInvoiceRequest $request, CreateInvoice $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $invoice = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['invoice' => new InvoiceResource($invoice)],
            __('Invoice created.'),
            201,
        );
    }

    public function showInvoice(ListInvoicesRequest $request, int $id): JsonResponse
    {
        $invoice = $this->resolveInvoice($request, $id);

        return $this->success(
            ['invoice' => new InvoiceResource($invoice->load('lines'))],
            __('Invoice retrieved.'),
        );
    }

    public function updateInvoice(UpdateInvoiceRequest $request, UpdateInvoice $action, int $id): JsonResponse
    {
        $invoice = $this->resolveInvoice($request, $id);
        $updated = $action->execute($invoice, $request->user(), $request->validated());

        return $this->success(
            ['invoice' => new InvoiceResource($updated)],
            __('Invoice updated.'),
        );
    }

    public function postInvoice(PostInvoiceRequest $request, PostInvoice $action, int $id): JsonResponse
    {
        $invoice = $this->resolveInvoice($request, $id);
        $posted = $action->execute($invoice, $request->user());

        return $this->success(
            ['invoice' => new InvoiceResource($posted)],
            __('Invoice posted.'),
        );
    }

    public function voidInvoice(VoidInvoiceRequest $request, VoidInvoice $action, int $id): JsonResponse
    {
        $invoice = $this->resolveInvoice($request, $id);
        $voided = $action->execute($invoice, $request->user());

        return $this->success(
            ['invoice' => new InvoiceResource($voided)],
            __('Invoice voided.'),
        );
    }

    // --- Resolution helpers ----------------------------------------------

    private function resolveJournalEntry(Request $request, int $id): JournalEntry
    {
        return JournalEntry::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveInvoice(Request $request, int $id): Invoice
    {
        return Invoice::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    // --- Bills ------------------------------------------------------------

    public function bills(ListBillsRequest $request, ListBills $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $bills = $action->execute($companyId, $request->only(['partner_id', 'status', 'per_page']));

        return $this->success(
            [
                'bills' => BillResource::collection($bills->items()),
                'pagination' => [
                    'current_page' => $bills->currentPage(),
                    'last_page' => $bills->lastPage(),
                    'per_page' => $bills->perPage(),
                    'total' => $bills->total(),
                ],
            ],
            __('Bills retrieved.'),
        );
    }

    public function storeBill(CreateBillRequest $request, CreateBill $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $bill = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['bill' => new BillResource($bill)],
            __('Bill created.'),
            201,
        );
    }

    public function showBill(ListBillsRequest $request, int $id): JsonResponse
    {
        $bill = $this->resolveBill($request, $id);

        return $this->success(
            ['bill' => new BillResource($bill->load(['lines', 'approvalRequest.actions.user']))],
            __('Bill retrieved.'),
        );
    }

    public function updateBill(UpdateBillRequest $request, UpdateBill $action, int $id): JsonResponse
    {
        $bill = $this->resolveBill($request, $id);
        $updated = $action->execute($bill, $request->user(), $request->validated());

        return $this->success(
            ['bill' => new BillResource($updated)],
            __('Bill updated.'),
        );
    }

    public function postBill(PostBillRequest $request, PostBill $action, int $id): JsonResponse
    {
        $bill = $this->resolveBill($request, $id);
        $posted = $action->execute($bill, $request->user());

        return $this->success(
            ['bill' => new BillResource($posted)],
            __('Bill posted.'),
        );
    }

    public function voidBill(VoidBillRequest $request, VoidBill $action, int $id): JsonResponse
    {
        $bill = $this->resolveBill($request, $id);
        $voided = $action->execute($bill, $request->user());

        return $this->success(
            ['bill' => new BillResource($voided)],
            __('Bill voided.'),
        );
    }

    private function resolveBill(Request $request, int $id): Bill
    {
        return Bill::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    // --- Payments ---------------------------------------------------------

    public function payments(ListPaymentsRequest $request, ListPayments $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $payments = $action->execute($companyId, $request->only(['partner_id', 'payment_type', 'status', 'per_page']));

        return $this->success(
            [
                'payments' => PaymentResource::collection($payments->items()),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                ],
            ],
            __('Payments retrieved.'),
        );
    }

    public function storePayment(CreatePaymentRequest $request, CreatePayment $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $payment = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['payment' => new PaymentResource($payment)],
            __('Payment created.'),
            201,
        );
    }

    public function showPayment(ListPaymentsRequest $request, int $id): JsonResponse
    {
        $payment = $this->resolvePayment($request, $id);

        return $this->success(
            ['payment' => new PaymentResource($payment->load(['allocations', 'approvalRequest.actions.user']))],
            __('Payment retrieved.'),
        );
    }

    public function updatePayment(UpdatePaymentRequest $request, UpdatePayment $action, int $id): JsonResponse
    {
        $payment = $this->resolvePayment($request, $id);
        $updated = $action->execute($payment, $request->user(), $request->validated());

        return $this->success(
            ['payment' => new PaymentResource($updated)],
            __('Payment updated.'),
        );
    }

    public function postPayment(PostPaymentRequest $request, PostPayment $action, int $id): JsonResponse
    {
        $payment = $this->resolvePayment($request, $id);
        $posted = $action->execute($payment, $request->user());

        return $this->success(
            ['payment' => new PaymentResource($posted)],
            __('Payment posted.'),
        );
    }

    public function voidPayment(VoidPaymentRequest $request, VoidPayment $action, int $id): JsonResponse
    {
        $payment = $this->resolvePayment($request, $id);
        $voided = $action->execute($payment, $request->user());

        return $this->success(
            ['payment' => new PaymentResource($voided)],
            __('Payment voided.'),
        );
    }

    private function resolvePayment(Request $request, int $id): Payment
    {
        return Payment::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    // --- Approval Matrices ------------------------------------------------

    public function approvalMatrices(ListApprovalMatricesRequest $request, ListApprovalMatrices $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            ['approval_matrices' => ApprovalMatrixResource::collection($action->execute($companyId))],
            __('Approval matrices retrieved.'),
        );
    }

    public function storeApprovalMatrix(CreateApprovalMatrixRequest $request, CreateApprovalMatrix $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $matrix = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['approval_matrix' => new ApprovalMatrixResource($matrix)],
            __('Approval matrix created.'),
            201,
        );
    }

    public function updateApprovalMatrix(UpdateApprovalMatrixRequest $request, UpdateApprovalMatrix $action, int $id): JsonResponse
    {
        $resolved = $this->resolveApprovalMatrix($request, $id);
        $updated = $action->execute($resolved, $request->user(), $request->validated());

        return $this->success(
            ['approval_matrix' => new ApprovalMatrixResource($updated)],
            __('Approval matrix updated.'),
        );
    }

    public function destroyApprovalMatrix(UpdateApprovalMatrixRequest $request, DeleteApprovalMatrix $action, int $id): JsonResponse
    {
        $action->execute($this->resolveApprovalMatrix($request, $id));

        return $this->success(null, __('Approval matrix deleted.'));
    }

    private function resolveApprovalMatrix(Request $request, int $id): ApprovalMatrix
    {
        return ApprovalMatrix::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    // --- Approval Workflow -----------------------------------------------

    public function approvalRequests(ListApprovalRequestsRequest $request, ListApprovalRequests $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $requests = $action->execute($companyId, $request->only(['status', 'approvable_type', 'per_page']));

        return $this->success(
            [
                'approval_requests' => ApprovalRequestResource::collection($requests->items()),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ],
            ],
            __('Approval requests retrieved.'),
        );
    }

    public function submitBillApproval(SubmitApprovalRequest $request, int $id): JsonResponse
    {
        $bill = $this->resolveBill($request, $id);
        $approvalRequest = app(ApprovalService::class)->submit($bill, $request->user());

        return $this->success(
            ['approval_request' => new ApprovalRequestResource($approvalRequest)],
            __('Bill submitted for approval.'),
        );
    }

    public function submitPaymentApproval(SubmitApprovalRequest $request, int $id): JsonResponse
    {
        $payment = $this->resolvePayment($request, $id);
        $approvalRequest = app(ApprovalService::class)->submit($payment, $request->user());

        return $this->success(
            ['approval_request' => new ApprovalRequestResource($approvalRequest)],
            __('Payment submitted for approval.'),
        );
    }

    public function actOnApproval(ActApprovalRequest $request, int $id): JsonResponse
    {
        $approvalRequest = $this->resolveApprovalRequest($request, $id);
        $action = $request->input('action');
        $remark = $request->input('remark');

        $updated = app(ApprovalService::class)->act(
            $approvalRequest,
            $request->user(),
            $action,
            $remark
        );

        return $this->success(
            ['approval_request' => new ApprovalRequestResource($updated->load('actions'))],
            __('Approval request acted upon.'),
        );
    }

    private function resolveApprovalRequest(Request $request, int $id): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    // --- Tax Returns ------------------------------------------------------

    public function taxReturns(ListTaxReturnsRequest $request, ListTaxReturns $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $taxReturns = $action->execute($companyId);

        return $this->success(
            ['tax_returns' => TaxReturnResource::collection($taxReturns)],
            __('Tax returns retrieved.')
        );
    }

    public function storeTaxReturn(GenerateTaxReturnRequest $request, GenerateTaxReturn $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $taxReturn = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['tax_return' => new TaxReturnResource($taxReturn->load('lines'))],
            __('Tax return generated.'),
            201
        );
    }

    public function showTaxReturn(ListTaxReturnsRequest $request, GetTaxReturn $action, int $id): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $taxReturn = $action->execute($companyId, $id);

        return $this->success(
            ['tax_return' => new TaxReturnResource($taxReturn)],
            __('Tax return retrieved.')
        );
    }

    public function finalizeTaxReturn(FinalizeTaxReturnRequest $request, FinalizeTaxReturn $action, int $id): JsonResponse
    {
        $taxReturn = $this->resolveTaxReturn($request, $id);
        $finalized = $action->execute($taxReturn, $request->user());

        return $this->success(
            ['tax_return' => new TaxReturnResource($finalized->load('lines'))],
            __('Tax return finalized.')
        );
    }

    public function destroyTaxReturn(DeleteTaxReturnRequest $request, DeleteTaxReturn $action, int $id): JsonResponse
    {
        $taxReturn = $this->resolveTaxReturn($request, $id);
        $action->execute($taxReturn);

        return $this->success(
            null,
            __('Tax return deleted.')
        );
    }

    private function resolveTaxReturn(Request $request, int $id): TaxReturn
    {
        return TaxReturn::query()
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }
}

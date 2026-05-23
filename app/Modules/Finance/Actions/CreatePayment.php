<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Partners\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePayment
{
    /**
     * Create a draft payment with optional allocations.
     *
     * @param  array{
     *     partner_id: int,
     *     branch_id?: int|null,
     *     payment_number?: string|null,
     *     payment_type: string,
     *     payment_date: string,
     *     payment_method: string,
     *     amount: string|float|numeric,
     *     currency_id?: int|null,
     *     exchange_rate?: string|float|numeric|null,
     *     cash_account_id: int,
     *     notes?: string|null,
     *     allocations?: array<int, array{
     *         invoice_id?: int|null,
     *         bill_id?: int|null,
     *         amount: string|float|numeric
     *     }>
     * }  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): Payment
    {
        // 1. Validate partner
        $partner = Partner::query()
            ->where('company_id', $companyId)
            ->find($data['partner_id']);

        if (! $partner) {
            throw ValidationException::withMessages([
                'partner_id' => [__('The selected partner is invalid or does not belong to this company.')],
            ]);
        }

        // 2. Validate cash account
        $cashAccount = Account::query()
            ->forCompany($companyId)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->where('type', 'asset')
            ->find($data['cash_account_id']);

        if (! $cashAccount) {
            throw ValidationException::withMessages([
                'cash_account_id' => [__('The cash account must be an active, postable asset account.')],
            ]);
        }

        $paymentAmount = (string) $data['amount'];
        $allocations = $data['allocations'] ?? [];

        // 3. Validate allocations
        $totalAllocationAmount = '0.0000';
        $validatedAllocations = [];

        foreach ($allocations as $index => $alloc) {
            $allocAmount = (string) $alloc['amount'];
            if (bccomp($allocAmount, '0.0000', 4) <= 0) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => [__('Allocation amount must be greater than zero.')],
                ]);
            }

            $totalAllocationAmount = bcadd($totalAllocationAmount, $allocAmount, 4);

            if ($data['payment_type'] === 'inbound') {
                if (empty($alloc['invoice_id'])) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [__('Invoice ID is required for inbound payment allocations.')],
                    ]);
                }
                $invoice = Invoice::query()->forCompany($companyId)->find($alloc['invoice_id']);
                if (! $invoice) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [__('The selected invoice does not belong to this company.')],
                    ]);
                }
                if (! in_array($invoice->status, ['posted', 'partially_paid'])) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [__('Only posted or partially paid invoices can receive allocations.')],
                    ]);
                }
                // Calculate remaining unpaid balance
                $remaining = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 4);
                if (bccomp($allocAmount, $remaining, 4) > 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => [__('Allocation amount exceeds the remaining unpaid balance of the invoice.')],
                    ]);
                }
                $validatedAllocations[] = [
                    'invoice_id' => $invoice->id,
                    'bill_id' => null,
                    'amount' => $allocAmount,
                ];
            } else { // outbound
                if (empty($alloc['bill_id'])) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.bill_id" => [__('Bill ID is required for outbound payment allocations.')],
                    ]);
                }
                $bill = Bill::query()->forCompany($companyId)->find($alloc['bill_id']);
                if (! $bill) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.bill_id" => [__('The selected bill does not belong to this company.')],
                    ]);
                }
                if (! in_array($bill->status, ['posted', 'partially_paid'])) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.bill_id" => [__('Only posted or partially paid bills can receive allocations.')],
                    ]);
                }
                // Calculate remaining unpaid balance
                $remaining = bcsub((string) $bill->total, (string) $bill->amount_paid, 4);
                if (bccomp($allocAmount, $remaining, 4) > 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => [__('Allocation amount exceeds the remaining unpaid balance of the bill.')],
                    ]);
                }
                $validatedAllocations[] = [
                    'invoice_id' => null,
                    'bill_id' => $bill->id,
                    'amount' => $allocAmount,
                ];
            }
        }

        // Validate total allocation amount does not exceed payment amount
        if (bccomp($totalAllocationAmount, $paymentAmount, 4) > 0) {
            throw ValidationException::withMessages([
                'amount' => [__('The total allocation amount cannot exceed the payment amount.')],
            ]);
        }

        return DB::transaction(function () use ($companyId, $user, $data, $validatedAllocations, $paymentAmount): Payment {
            $paymentNumber = $data['payment_number'] ?? ('PAY-'.date('Ymd').'-'.strtoupper(Str::random(6)));

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'payment_number' => $paymentNumber,
                'partner_id' => $data['partner_id'],
                'payment_type' => $data['payment_type'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $paymentAmount,
                'currency_id' => $data['currency_id'] ?? 1, // Default to 1 (IDR or USD depending on seeder)
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'cash_account_id' => $data['cash_account_id'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($validatedAllocations as $alloc) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $alloc['invoice_id'],
                    'bill_id' => $alloc['bill_id'],
                    'amount' => $alloc['amount'],
                ]);
            }

            return $payment->load('allocations');
        });
    }
}

<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Services\ApprovalService;
use App\Modules\Partners\Actions\CheckPartnerBelongsToCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePayment
{
    public function __construct(private readonly CheckPartnerBelongsToCompany $companyPartner) {}

    /**
     * Update a draft payment.
     *
     * @param  array{
     *     partner_id?: int,
     *     branch_id?: int|null,
     *     payment_number?: string,
     *     payment_type?: string,
     *     payment_date?: string,
     *     payment_method?: string,
     *     amount?: string|float|numeric,
     *     currency_id?: int,
     *     exchange_rate?: string|float|numeric,
     *     cash_account_id?: int,
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
    public function execute(Payment $payment, User $user, array $data): Payment
    {
        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft payments can be updated.')],
            ]);
        }

        $companyId = $payment->company_id;

        // 1. Validate partner if updated
        if (isset($data['partner_id']) && ! $this->companyPartner->execute($companyId, $data['partner_id'])) {
            throw ValidationException::withMessages([
                'partner_id' => [__('The selected partner is invalid or does not belong to this company.')],
            ]);
        }

        // 2. Validate cash account if updated
        if (isset($data['cash_account_id'])) {
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
        }

        $paymentType = $data['payment_type'] ?? $payment->payment_type;
        $paymentAmount = isset($data['amount']) ? (string) $data['amount'] : (string) $payment->amount;

        // 3. Resolve allocations to validate
        $totalAllocationAmount = '0.0000';
        $validatedAllocations = [];
        $hasAllocations = array_key_exists('allocations', $data);

        if ($hasAllocations) {
            $allocations = $data['allocations'] ?? [];
            foreach ($allocations as $index => $alloc) {
                $allocAmount = (string) $alloc['amount'];
                if (bccomp($allocAmount, '0.0000', 4) <= 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => [__('Allocation amount must be greater than zero.')],
                    ]);
                }

                $totalAllocationAmount = bcadd($totalAllocationAmount, $allocAmount, 4);

                if ($paymentType === 'inbound') {
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
        } else {
            // Validate existing allocations against new amount if amount changed
            foreach ($payment->allocations as $alloc) {
                $totalAllocationAmount = bcadd($totalAllocationAmount, (string) $alloc->amount, 4);
            }
        }

        // Validate total allocation amount does not exceed payment amount
        if (bccomp($totalAllocationAmount, $paymentAmount, 4) > 0) {
            throw ValidationException::withMessages([
                'amount' => [__('The total allocation amount cannot exceed the payment amount.')],
            ]);
        }

        return DB::transaction(function () use ($payment, $user, $data, $hasAllocations, $validatedAllocations, $paymentAmount): Payment {
            // Update attributes
            $fillData = [];
            foreach (['partner_id', 'branch_id', 'payment_number', 'payment_type', 'payment_date', 'payment_method', 'currency_id', 'exchange_rate', 'cash_account_id', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fillData[$field] = $data[$field];
                }
            }
            $fillData['amount'] = $paymentAmount;
            $payment->fill($fillData);
            $payment->updated_by = $user->id;
            $payment->save();

            if ($hasAllocations) {
                $payment->allocations()->delete();
                foreach ($validatedAllocations as $alloc) {
                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'invoice_id' => $alloc['invoice_id'],
                        'bill_id' => $alloc['bill_id'],
                        'amount' => $alloc['amount'],
                    ]);
                }
            }

            app(ApprovalService::class)->reset($payment);

            return $payment->load('allocations');
        });
    }
}

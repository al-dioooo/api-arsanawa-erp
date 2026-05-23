<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SalePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddSalePayment
{
    /**
     * @param  array{method: string, amount: numeric|string|float, reference?: string|null, paid_at?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user, array $data): Sale
    {
        if (! in_array($sale->status, ['draft', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'sale' => [__('Payments can only be changed before completion.')],
            ]);
        }

        return DB::transaction(function () use ($sale, $user, $data): Sale {
            $newAmountPaid = bcadd((string) $sale->amount_paid, number_format((float) $data['amount'], 4, '.', ''), 4);

            if (bccomp($newAmountPaid, (string) $sale->total, 4) > 0) {
                throw ValidationException::withMessages([
                    'amount' => [__('Payments cannot exceed the sale total.')],
                ]);
            }

            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $data['method'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'created_by' => $user->id,
            ]);

            $sale->update([
                'amount_paid' => $newAmountPaid,
                'updated_by' => $user->id,
            ]);

            return $sale->load(['lines', 'payments', 'register']);
        });
    }
}

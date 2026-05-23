<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SalePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveSalePayment
{
    /**
     * @throws ValidationException
     */
    public function execute(Sale $sale, SalePayment $payment, User $user): Sale
    {
        if (! in_array($sale->status, ['draft', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'sale' => [__('Payments can only be changed before completion.')],
            ]);
        }

        return DB::transaction(function () use ($sale, $payment, $user): Sale {
            $payment->delete();

            $sale->update([
                'amount_paid' => $sale->payments()->sum('amount'),
                'updated_by' => $user->id,
            ]);

            return $sale->load(['lines', 'payments', 'register']);
        });
    }
}

<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\SalePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseShift
{
    /**
     * @param  array{counted_cash: numeric|string|float, notes?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function execute(CashierShift $shift, User $user, array $data): CashierShift
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages([
                'shift' => [__('Only open shifts can be closed.')],
            ]);
        }

        return DB::transaction(function () use ($shift, $user, $data): CashierShift {
            $cashSales = number_format((float) SalePayment::query()
                ->where('method', 'cash')
                ->whereHas('sale', function ($query) use ($shift): void {
                    $query->where('cashier_shift_id', $shift->id)
                        ->where('status', 'completed');
                })
                ->sum('amount'), 4, '.', '');
            $expectedCash = bcadd((string) $shift->opening_float, $cashSales, 4);
            $countedCash = number_format((float) $data['counted_cash'], 4, '.', '');
            $variance = bcsub($countedCash, $expectedCash, 4);

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'cash_variance' => $variance,
                'notes' => $data['notes'] ?? $shift->notes,
                'updated_by' => $user->id,
            ]);

            return $shift->load(['register', 'user']);
        });
    }
}

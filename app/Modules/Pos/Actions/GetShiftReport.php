<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\SalePayment;

class GetShiftReport
{
    /**
     * @return array<string, mixed>
     */
    public function execute(CashierShift $shift): array
    {
        $cashSales = $this->paymentTotal($shift, 'cash');
        $nonCashSales = $this->paymentTotal($shift, null, true);
        $expectedCash = $shift->expected_cash ?? bcadd((string) $shift->opening_float, $cashSales, 4);
        $countedCash = $shift->counted_cash;
        $variance = $shift->cash_variance;

        return [
            'shift_id' => $shift->id,
            'register_id' => $shift->register_id,
            'status' => $shift->status,
            'opening_float' => $shift->opening_float,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCashSales,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'cash_variance' => $variance,
        ];
    }

    private function paymentTotal(CashierShift $shift, ?string $method, bool $excludeCash = false): string
    {
        $query = SalePayment::query()
            ->whereHas('sale', function ($query) use ($shift): void {
                $query->where('cashier_shift_id', $shift->id)
                    ->where('status', 'completed');
            });

        if ($method !== null) {
            $query->where('method', $method);
        }

        if ($excludeCash) {
            $query->where('method', '!=', 'cash');
        }

        return number_format((float) $query->sum('amount'), 4, '.', '');
    }
}

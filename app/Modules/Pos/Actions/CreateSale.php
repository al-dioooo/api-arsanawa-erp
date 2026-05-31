<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSale
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $companyId, User $user, array $data): Sale
    {
        $orderDate = $data['order_date'] ?? now()->toDateString();
        $calculations = $this->checkoutService->calculate($companyId, $data['lines'], $orderDate);

        return DB::transaction(function () use ($companyId, $user, $data, $orderDate, $calculations): Sale {
            $registerId = $data['register_id'] ?? null;
            $shiftId = $data['cashier_shift_id'] ?? $this->currentShiftId($companyId, $registerId);

            $sale = Sale::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'register_id' => $registerId,
                'cashier_shift_id' => $shiftId,
                'sale_number' => $data['sale_number'] ?? ('POS-'.date('Ymd').'-'.strtoupper(Str::random(6))),
                'type' => $data['type'],
                'partner_id' => $data['partner_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'status' => 'draft',
                'order_date' => $orderDate,
                'fulfilment_date' => $data['fulfilment_date'] ?? null,
                'fulfilment_time_window' => $data['fulfilment_time_window'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'currency_id' => $data['currency_id'] ?? 1,
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'subtotal' => $calculations['subtotal'],
                'discount_total' => $calculations['discount_total'],
                'tax_total' => $calculations['tax_total'],
                'total' => $calculations['total'],
                'amount_paid' => '0.0000',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->createLines($sale, $calculations['lines']);

            return $sale->load(['lines', 'payments', 'register']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function createLines(Sale $sale, array $lines): void
    {
        foreach ($lines as $line) {
            SaleLine::create(array_merge($line, ['sale_id' => $sale->id]));
        }
    }

    private function currentShiftId(int $companyId, ?int $registerId): ?int
    {
        if ($registerId === null) {
            return null;
        }

        return CashierShift::query()
            ->forCompany($companyId)
            ->where('register_id', $registerId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->value('id');
    }
}

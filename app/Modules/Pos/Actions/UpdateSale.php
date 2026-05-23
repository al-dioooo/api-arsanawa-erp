<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSale
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user, array $data): Sale
    {
        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'sale' => [__('Only draft sales can be updated.')],
            ]);
        }

        return DB::transaction(function () use ($sale, $user, $data): Sale {
            $header = collect($data)->except('lines')->all();
            if ($header !== []) {
                $sale->fill($header);
            }

            if (isset($data['lines'])) {
                $on = isset($data['order_date'])
                    ? (string) $data['order_date']
                    : $sale->order_date->toDateString();

                $calculations = $this->checkoutService->calculate(
                    $sale->company_id,
                    $data['lines'],
                    $on,
                );

                $sale->fill([
                    'subtotal' => $calculations['subtotal'],
                    'discount_total' => $calculations['discount_total'],
                    'tax_total' => $calculations['tax_total'],
                    'total' => $calculations['total'],
                ]);

                $sale->lines()->delete();
                foreach ($calculations['lines'] as $line) {
                    SaleLine::create(array_merge($line, ['sale_id' => $sale->id]));
                }
            }

            $sale->updated_by = $user->id;
            $sale->save();

            return $sale->load(['lines', 'payments', 'register']);
        });
    }
}

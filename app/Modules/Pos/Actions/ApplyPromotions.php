<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleLine;
use App\Modules\Pos\Models\SalePromotion;
use App\Modules\Pos\Services\CheckoutService;
use App\Modules\Pos\Services\PromotionEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyPromotions
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PromotionEvaluator $promotionEvaluator,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user): Sale
    {
        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'sale' => [__('Only draft sales can apply promotions.')],
            ]);
        }

        return DB::transaction(function () use ($sale, $user): Sale {
            $baseLines = $sale->lines()
                ->where('is_giveaway', false)
                ->get()
                ->map(fn (SaleLine $line): array => [
                    'product_variant_id' => $line->product_variant_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'discount' => $line->discount,
                    'tax_rate_id' => $line->tax_rate_id,
                    'revenue_account_id' => $line->revenue_account_id,
                    'is_giveaway' => false,
                ])
                ->all();

            $baseCalculations = $this->checkoutService->calculate(
                $sale->company_id,
                $baseLines,
                $sale->order_date->toDateString(),
            );

            $evaluation = $this->promotionEvaluator->evaluate(
                $sale->company_id,
                $sale->branch_id,
                $baseCalculations['lines'],
                $sale->order_date->toDateString(),
            );

            $allLines = array_merge($baseLines, $evaluation['giveaways']);
            $calculations = $this->checkoutService->calculate(
                $sale->company_id,
                $allLines,
                $sale->order_date->toDateString(),
            );

            $promotionTotal = array_reduce($evaluation['promotions'], static function (string $carry, array $promotion): string {
                return bcadd($carry, (string) $promotion['amount'], 4);
            }, '0.0000');

            $sale->lines()->delete();
            foreach ($calculations['lines'] as $line) {
                SaleLine::create(array_merge($line, ['sale_id' => $sale->id]));
            }

            $sale->promotions()->delete();
            foreach ($evaluation['promotions'] as $promotion) {
                SalePromotion::create(array_merge($promotion, ['sale_id' => $sale->id]));
            }

            $sale->update([
                'subtotal' => $calculations['subtotal'],
                'discount_total' => bcadd($calculations['discount_total'], $promotionTotal, 4),
                'tax_total' => $calculations['tax_total'],
                'total' => bcsub($calculations['total'], $promotionTotal, 4),
                'updated_by' => $user->id,
            ]);

            return $sale->load(['lines', 'payments', 'promotions', 'register']);
        });
    }
}

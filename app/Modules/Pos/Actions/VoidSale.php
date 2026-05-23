<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Finance\Services\PostingService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidSale
{
    public function __construct(
        private readonly PostingService $postingService,
        private readonly StockService $stockService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user): Sale
    {
        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages([
                'sale' => [__('Only completed sales can be voided.')],
            ]);
        }

        return DB::transaction(function () use ($sale, $user): Sale {
            $sale->load(['revenueJournalEntry', 'cogsJournalEntry']);

            if ($sale->revenueJournalEntry) {
                $this->postingService->reverse($sale->revenueJournalEntry, $user);
            }

            if ($sale->cogsJournalEntry) {
                $this->postingService->reverse($sale->cogsJournalEntry, $user);
            }

            StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->where('type', 'issue')
                ->get()
                ->each(function (StockMovement $movement) use ($sale, $user): void {
                    $this->stockService->recordReceipt([
                        'company_id' => $sale->company_id,
                        'branch_id' => $movement->branch_id,
                        'product_variant_id' => $movement->product_variant_id,
                        'quantity' => ltrim((string) $movement->quantity, '-'),
                        'unit_cost' => $movement->unit_cost,
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'notes' => 'Void POS sale '.$sale->sale_number,
                        'created_by' => $user->id,
                    ]);
                });

            $sale->update([
                'status' => 'void',
                'updated_by' => $user->id,
            ]);

            return $sale->load(['lines', 'payments', 'promotions', 'register']);
        });
    }
}

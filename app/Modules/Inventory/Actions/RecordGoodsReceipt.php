<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\ProductUnitStockResolver;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordGoodsReceipt
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ProductUnitStockResolver $resolver,
    ) {}

    /**
     * Create a goods-receipt document header and, for each line, record the stock
     * receipt (lot + ledger movement) it produces — linking the line back to the
     * movement/lot so the document and the inventory ledger stay in lockstep.
     *
     * @param  array{
     *     branch_id: int,
     *     partner_id: int,
     *     receipt_number?: string|null,
     *     delivery_note_number?: string|null,
     *     receipt_date?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         product_unit_id?: int|null,
     *         product_variant_id?: int|null,
     *         quantity: float|numeric,
     *         unit_cost: float|numeric,
     *         lot_number?: string|null,
     *         expiry_date?: string|null,
     *         notes?: string|null
     *     }>
     * }  $data
     */
    public function execute(int $companyId, User $user, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($companyId, $user, $data): GoodsReceipt {
            $receiptDate = $data['receipt_date'] ?? now()->toDateString();

            $receipt = GoodsReceipt::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'receipt_number' => $data['receipt_number'] ?? ('STB-'.date('Ymd').'-'.mb_strtoupper(Str::random(6))),
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'partner_id' => $data['partner_id'],
                'receipt_date' => $receiptDate,
                'status' => 'received',
                'total_cost' => '0.0000',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $totalCost = '0';

            foreach ($data['lines'] as $line) {
                $stockItem = $this->resolver->resolve($companyId, $line, $user->id);

                // Normalize to plain 4-decimal strings before any bcmath so exponent
                // notation never reaches it — the loose `numeric` rule admits values
                // like 0.0000001, which PHP stringifies as "1.0E-7" and bcmul rejects.
                $quantity = $this->toDecimalString($line['quantity']);
                $unitCost = $this->toDecimalString($line['unit_cost']);

                $lot = $this->stockService->recordReceipt([
                    'company_id' => $companyId,
                    'branch_id' => $data['branch_id'],
                    'product_variant_id' => $stockItem['product_variant_id'],
                    'product_unit_id' => $stockItem['product_unit_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'lot_number' => $line['lot_number'] ?? null,
                    'received_at' => $receiptDate,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'notes' => $line['notes'] ?? null,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'created_by' => $user->id,
                ]);

                // recordReceipt() creates exactly one 'receipt' movement for the new lot.
                $movement = StockMovement::query()
                    ->where('stock_lot_id', $lot->id)
                    ->where('type', 'receipt')
                    ->latest('id')
                    ->first();

                $lineTotal = bcmul($quantity, $unitCost, 4);

                $receipt->lines()->create([
                    'product_variant_id' => $stockItem['product_variant_id'],
                    'product_unit_id' => $stockItem['product_unit_id'] ?? null,
                    'stock_lot_id' => $lot->id,
                    'stock_movement_id' => $movement?->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'lot_number' => $line['lot_number'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);

                $totalCost = bcadd($totalCost, $lineTotal, 4);
            }

            $receipt->update(['total_cost' => $totalCost]);

            return $receipt->load(['partner', 'lines']);
        });
    }

    /**
     * Coerce a validated numeric value into a plain fixed-point decimal string
     * (matching the decimal:4 casts used across inventory), stripping any
     * exponent notation that bcmath cannot parse.
     */
    private function toDecimalString(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}

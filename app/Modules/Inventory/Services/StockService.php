<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * FIFO consumption fetches and row-locks lots this many at a time so an
     * issue touching two lots never locks a thousand-lot history.
     */
    public const LOT_CHUNK_SIZE = 50;

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordReceipt(array $data): StockLot
    {
        return DB::transaction(function () use ($data): StockLot {
            $lot = StockLot::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'product_variant_id' => $data['product_variant_id'],
                'product_unit_id' => $data['product_unit_id'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'received_quantity' => $data['quantity'],
                'remaining_quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'],
                'received_at' => $data['received_at'] ?? now()->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'production_date' => $data['production_date'] ?? null,
                'batch_metadata' => $data['batch_metadata'] ?? null,
                'status' => 'active',
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['created_by'] ?? null,
            ]);

            StockMovement::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'product_variant_id' => $data['product_variant_id'],
                'product_unit_id' => $data['product_unit_id'] ?? null,
                'stock_lot_id' => $lot->id,
                'type' => 'receipt',
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'occurred_at' => now(),
                'created_by' => $data['created_by'] ?? null,
            ]);

            return $lot;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function recordIssue(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $quantity = (float) $data['quantity'];
            $onHand = isset($data['product_unit_id'])
                ? $this->onHandForProductUnit((int) $data['product_unit_id'], $data['branch_id'])
                : $this->onHand($data['product_variant_id'], $data['branch_id']);

            if (bccomp((string) $onHand, (string) $quantity, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock. Available: {$onHand}, requested: {$quantity}."],
                ]);
            }

            $this->consumeFifo(
                (int) $data['product_variant_id'],
                (int) $data['branch_id'],
                isset($data['product_unit_id']) ? (int) $data['product_unit_id'] : null,
                $quantity,
                function (StockLot $lot, float $consume) use ($data): void {
                    StockMovement::create([
                        'company_id' => $data['company_id'],
                        'branch_id' => $data['branch_id'],
                        'product_variant_id' => $data['product_variant_id'],
                        'product_unit_id' => $data['product_unit_id'] ?? null,
                        'stock_lot_id' => $lot->id,
                        'type' => 'issue',
                        'quantity' => -$consume,
                        'unit_cost' => $lot->unit_cost,
                        'reference_type' => $data['reference_type'] ?? null,
                        'reference_id' => $data['reference_id'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'occurred_at' => now(),
                        'created_by' => $data['created_by'] ?? null,
                    ]);
                },
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function recordAdjustment(array $data): void
    {
        $quantity = (float) $data['quantity'];

        if ($quantity > 0) {
            $this->recordReceipt(array_merge($data, [
                'unit_cost' => $data['unit_cost'] ?? 0,
                'received_at' => $data['received_at'] ?? now()->toDateString(),
            ]));
        } else {
            $this->recordIssue(array_merge($data, [
                'quantity' => abs($quantity),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function recordTransfer(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data): StockTransfer {
            $transfer = StockTransfer::create([
                'company_id' => $data['company_id'],
                'from_branch_id' => $data['from_branch_id'],
                'to_branch_id' => $data['to_branch_id'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['created_by'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);

                // FIFO-consume at the source branch
                $quantity = (float) $item['quantity'];
                $onHand = isset($item['product_unit_id'])
                    ? $this->onHandForProductUnit((int) $item['product_unit_id'], $data['from_branch_id'])
                    : $this->onHand($item['product_variant_id'], $data['from_branch_id']);

                if (bccomp((string) $onHand, (string) $quantity, 4) < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Insufficient stock at source branch for variant {$item['product_variant_id']}. Available: {$onHand}, requested: {$quantity}."],
                    ]);
                }

                $this->consumeFifo(
                    (int) $item['product_variant_id'],
                    (int) $data['from_branch_id'],
                    isset($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
                    $quantity,
                    function (StockLot $lot, float $consume) use ($data, $item, $transfer): void {
                        // Transfer out movement
                        StockMovement::create([
                            'company_id' => $data['company_id'],
                            'branch_id' => $data['from_branch_id'],
                            'product_variant_id' => $item['product_variant_id'],
                            'product_unit_id' => $item['product_unit_id'] ?? null,
                            'stock_lot_id' => $lot->id,
                            'type' => 'transfer_out',
                            'quantity' => -$consume,
                            'unit_cost' => $lot->unit_cost,
                            'reference_type' => StockTransfer::class,
                            'reference_id' => $transfer->id,
                            'occurred_at' => now(),
                            'created_by' => $data['created_by'] ?? null,
                        ]);

                        // Create lot at destination preserving original cost
                        $destLot = StockLot::create([
                            'company_id' => $data['company_id'],
                            'branch_id' => $data['to_branch_id'],
                            'product_variant_id' => $item['product_variant_id'],
                            'product_unit_id' => $item['product_unit_id'] ?? null,
                            'lot_number' => $lot->lot_number,
                            'received_quantity' => $consume,
                            'remaining_quantity' => $consume,
                            'unit_cost' => $lot->unit_cost,
                            'received_at' => $lot->received_at,
                            'expiry_date' => $lot->expiry_date,
                            'status' => 'active',
                            'created_by' => $data['created_by'] ?? null,
                            'updated_by' => $data['created_by'] ?? null,
                        ]);

                        // Transfer in movement
                        StockMovement::create([
                            'company_id' => $data['company_id'],
                            'branch_id' => $data['to_branch_id'],
                            'product_variant_id' => $item['product_variant_id'],
                            'product_unit_id' => $item['product_unit_id'] ?? null,
                            'stock_lot_id' => $destLot->id,
                            'type' => 'transfer_in',
                            'quantity' => $consume,
                            'unit_cost' => $lot->unit_cost,
                            'reference_type' => StockTransfer::class,
                            'reference_id' => $transfer->id,
                            'occurred_at' => now(),
                            'created_by' => $data['created_by'] ?? null,
                        ]);
                    },
                );
            }

            return $transfer->load('items');
        });
    }

    /**
     * FIFO-consume lots for a variant at a branch, fetching and row-locking
     * only LOT_CHUNK_SIZE lots at a time. Fully consumed lots drop out of the
     * remaining_quantity filter, so re-querying naturally advances; a lot is
     * only ever partially consumed when nothing remains to consume, at which
     * point the loop exits. Callers must have verified sufficient on-hand
     * stock inside the same transaction.
     *
     * @param  callable(StockLot, float): void  $onConsume
     */
    private function consumeFifo(int $variantId, int $branchId, ?int $productUnitId, float $quantity, callable $onConsume): void
    {
        $remaining = (string) $quantity;

        while (bccomp($remaining, '0', 4) > 0) {
            $lots = StockLot::query()
                ->where('product_variant_id', $variantId)
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->when($productUnitId !== null, fn ($query) => $query->where('product_unit_id', $productUnitId))
                ->orderBy('received_at')
                ->orderBy('id')
                ->limit(self::LOT_CHUNK_SIZE)
                ->lockForUpdate()
                ->get();

            if ($lots->isEmpty()) {
                break;
            }

            foreach ($lots as $lot) {
                $consume = min((float) $lot->remaining_quantity, (float) $remaining);
                $lot->remaining_quantity = bcsub((string) $lot->remaining_quantity, (string) $consume, 4);

                if (bccomp((string) $lot->remaining_quantity, '0', 4) <= 0) {
                    $lot->status = 'depleted';
                }

                $lot->save();

                $onConsume($lot, $consume);

                $remaining = bcsub($remaining, (string) $consume, 4);

                if (bccomp($remaining, '0', 4) <= 0) {
                    break;
                }
            }
        }
    }

    public function onHand(int $variantId, int $branchId): float
    {
        return (float) StockLot::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->sum('remaining_quantity');
    }

    public function onHandForProductUnit(int $productUnitId, int $branchId): float
    {
        return (float) StockLot::query()
            ->where('product_unit_id', $productUnitId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->sum('remaining_quantity');
    }

    public function valuation(int $companyId, ?int $branchId = null, ?int $productUnitId = null): float
    {
        $query = StockLot::query()
            ->where('company_id', $companyId)
            ->where('status', 'active');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($productUnitId !== null) {
            $query->where('product_unit_id', $productUnitId);
        }

        return (float) $query->selectRaw('SUM(remaining_quantity * unit_cost) as total')
            ->value('total') ?? 0;
    }
}

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
     * @param  array<string, mixed>  $data
     */
    public function recordReceipt(array $data): StockLot
    {
        return DB::transaction(function () use ($data): StockLot {
            $lot = StockLot::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'product_variant_id' => $data['product_variant_id'],
                'lot_number' => $data['lot_number'] ?? null,
                'received_quantity' => $data['quantity'],
                'remaining_quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'],
                'received_at' => $data['received_at'] ?? now()->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => 'active',
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['created_by'] ?? null,
            ]);

            StockMovement::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'product_variant_id' => $data['product_variant_id'],
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
            $remaining = (float) $data['quantity'];
            $onHand = $this->onHand($data['product_variant_id'], $data['branch_id']);

            if (bccomp((string) $onHand, (string) $remaining, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock. Available: {$onHand}, requested: {$remaining}."],
                ]);
            }

            $lots = StockLot::query()
                ->where('product_variant_id', $data['product_variant_id'])
                ->where('branch_id', $data['branch_id'])
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if (bccomp((string) $remaining, '0', 4) <= 0) {
                    break;
                }

                $consume = min((float) $lot->remaining_quantity, $remaining);
                $lot->remaining_quantity = bcsub((string) $lot->remaining_quantity, (string) $consume, 4);

                if (bccomp((string) $lot->remaining_quantity, '0', 4) <= 0) {
                    $lot->status = 'depleted';
                }

                $lot->save();

                StockMovement::create([
                    'company_id' => $data['company_id'],
                    'branch_id' => $data['branch_id'],
                    'product_variant_id' => $data['product_variant_id'],
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

                $remaining = bcsub((string) $remaining, (string) $consume, 4);
            }
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
                    'quantity' => $item['quantity'],
                ]);

                // FIFO-consume at the source branch
                $remaining = (float) $item['quantity'];
                $onHand = $this->onHand($item['product_variant_id'], $data['from_branch_id']);

                if (bccomp((string) $onHand, (string) $remaining, 4) < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Insufficient stock at source branch for variant {$item['product_variant_id']}. Available: {$onHand}, requested: {$remaining}."],
                    ]);
                }

                $lots = StockLot::query()
                    ->where('product_variant_id', $item['product_variant_id'])
                    ->where('branch_id', $data['from_branch_id'])
                    ->where('status', 'active')
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if (bccomp((string) $remaining, '0', 4) <= 0) {
                        break;
                    }

                    $consume = min((float) $lot->remaining_quantity, $remaining);
                    $lot->remaining_quantity = bcsub((string) $lot->remaining_quantity, (string) $consume, 4);

                    if (bccomp((string) $lot->remaining_quantity, '0', 4) <= 0) {
                        $lot->status = 'depleted';
                    }

                    $lot->save();

                    // Transfer out movement
                    StockMovement::create([
                        'company_id' => $data['company_id'],
                        'branch_id' => $data['from_branch_id'],
                        'product_variant_id' => $item['product_variant_id'],
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
                        'stock_lot_id' => $destLot->id,
                        'type' => 'transfer_in',
                        'quantity' => $consume,
                        'unit_cost' => $lot->unit_cost,
                        'reference_type' => StockTransfer::class,
                        'reference_id' => $transfer->id,
                        'occurred_at' => now(),
                        'created_by' => $data['created_by'] ?? null,
                    ]);

                    $remaining = bcsub((string) $remaining, (string) $consume, 4);
                }
            }

            return $transfer->load('items');
        });
    }

    public function onHand(int $variantId, int $branchId): float
    {
        return (float) StockLot::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->sum('remaining_quantity');
    }

    public function valuation(int $companyId, ?int $branchId = null): float
    {
        $query = StockLot::query()
            ->where('company_id', $companyId)
            ->where('status', 'active');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (float) $query->selectRaw('SUM(remaining_quantity * unit_cost) as total')
            ->value('total') ?? 0;
    }
}

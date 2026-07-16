<?php

// Inventory module — performance indexes for hot stock queries.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FIFO consume / on-hand queries filter by (product_variant_id, branch_id,
        // status) without company_id, so the existing company-led composite index
        // is not usable. Add an index that leads with the actual filter columns.
        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->index(['product_variant_id', 'branch_id', 'status'], 'stock_lots_fifo_idx');
        });

        // The movement ledger is paginated by occurred_at per company.
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['company_id', 'occurred_at'], 'stock_movements_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->dropIndex('stock_lots_fifo_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_history_idx');
        });
    }
};

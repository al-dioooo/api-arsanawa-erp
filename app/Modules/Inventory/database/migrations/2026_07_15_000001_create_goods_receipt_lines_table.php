<?php

// Inventory — goods receipt line items, each linked to the stock receipt movement it created

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            // The lot and movement this line produced when the receipt was recorded.
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('line_total', 19, 4);
            $table->string('lot_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('goods_receipt_id');
            $table->index('stock_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};

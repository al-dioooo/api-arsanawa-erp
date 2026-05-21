<?php

// Inventory — stock lots

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number')->nullable();
            $table->decimal('received_quantity', 15, 4);
            $table->decimal('remaining_quantity', 15, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->date('received_at');
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'product_variant_id', 'status'], 'stock_lots_lookup_idx');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};

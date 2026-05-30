<?php

// Inventory — add Product Unit reference to stock transfer items

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('product_units')
                ->cascadeOnDelete();
            $table->index('product_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_unit_id');
        });
    }
};

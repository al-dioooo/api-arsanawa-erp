<?php

// Inventory module — product unit operational references migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_variant_id')->constrained('product_units')->cascadeOnDelete();
            $table->index('product_unit_id');
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_variant_id')->constrained('product_units')->cascadeOnDelete();
            $table->index('product_unit_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_variant_id')->constrained('product_units')->cascadeOnDelete();
            $table->index('product_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
        });
    }
};

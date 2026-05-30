<?php

// Inventory module — product units / sellable SKUs migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 80);
            $table->string('barcode', 80)->nullable();
            $table->string('name', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('product_unit_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['product_unit_id', 'variant_id']);
            $table->index('variant_id');
        });

        DB::statement('CREATE UNIQUE INDEX product_units_company_barcode_unique ON product_units (company_id, barcode) WHERE barcode IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unit_variant');
        Schema::dropIfExists('product_units');
    }
};

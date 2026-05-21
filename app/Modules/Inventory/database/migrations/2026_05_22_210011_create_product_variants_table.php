<?php

// Inventory module — product variants migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->foreignId('purchase_uom_id')->nullable()->constrained('units_of_measure');
            $table->decimal('purchase_conversion_factor', 15, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
            $table->index('product_id');
        });

        // Barcode is unique per company only when present.
        DB::statement('CREATE UNIQUE INDEX product_variants_company_barcode_unique ON product_variants (company_id, barcode) WHERE barcode IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

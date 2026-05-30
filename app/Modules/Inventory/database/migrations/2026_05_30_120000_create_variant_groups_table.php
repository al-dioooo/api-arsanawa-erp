<?php

// Inventory module — variant groups migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('code', 80);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'name']);
            $table->index('unit_of_measure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_groups');
    }
};

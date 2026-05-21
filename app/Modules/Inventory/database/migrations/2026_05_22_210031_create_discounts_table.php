<?php

// Inventory — discounts (targets, dependencies, giveaways)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('calculation_type'); // percentage, amount
            $table->decimal('value', 19, 4);
            $table->integer('min_quantity')->nullable();
            $table->integer('starting_item_number')->nullable();
            $table->boolean('multiply')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('discount_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->string('target_type'); // variant, product, category
            $table->unsignedBigInteger('target_id');

            $table->index(['discount_id', 'target_type']);
        });

        Schema::create('discount_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('required_quantity');
        });

        Schema::create('discount_giveaways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('giveaway_quantity')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_giveaways');
        Schema::dropIfExists('discount_dependencies');
        Schema::dropIfExists('discount_targets');
        Schema::dropIfExists('discounts');
    }
};

<?php

// Inventory — rewards and reward targets

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('calculation_type'); // percentage, amount
            $table->decimal('value', 19, 4);
            $table->integer('min_quantity')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('reward_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->string('target_type'); // variant, product, category
            $table->unsignedBigInteger('target_id');

            $table->index(['reward_id', 'target_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_targets');
        Schema::dropIfExists('rewards');
    }
};

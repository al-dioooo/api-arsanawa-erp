<?php

// POS module — applied sale promotions

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('promotion_type');
            $table->unsignedBigInteger('promotion_id');
            $table->string('description');
            $table->decimal('amount', 19, 4);
            $table->timestamps();

            $table->index('sale_id');
            $table->index(['promotion_type', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_promotions');
    }
};

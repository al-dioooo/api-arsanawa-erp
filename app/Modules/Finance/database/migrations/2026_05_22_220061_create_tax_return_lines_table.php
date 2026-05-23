<?php

// Finance module — tax return lines (details for tax returns)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->morphs('source'); // source_type (Invoice, Bill), source_id
            $table->decimal('tax_amount', 19, 4);
            $table->timestamps();

            $table->index('tax_return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_return_lines');
    }
};

<?php

// Platform module — immutable spreadsheet import preview rows

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->json('normalized')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};

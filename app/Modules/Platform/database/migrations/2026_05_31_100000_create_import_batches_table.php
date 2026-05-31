<?php

// Platform module — shared spreadsheet import batches

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 80);
            $table->string('source', 20);
            $table->string('source_path');
            $table->string('source_url', 2048)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('sheets')->nullable();
            $table->string('selected_sheet')->nullable();
            $table->string('status')->default('inspected');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'kind', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};

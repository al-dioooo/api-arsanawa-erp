<?php

// Partners module — partners migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // customer, supplier, both
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_identifier', 50)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->decimal('transaction_limit', 15, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'status']);
        });

        // Partial unique index: code must be unique per company where code is not null
        DB::statement('CREATE UNIQUE INDEX partners_company_id_code_unique ON partners (company_id, code) WHERE code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};

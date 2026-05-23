<?php

// Finance module — approval matrices (amount-banded approval rules per company)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); // bill, payment
            $table->decimal('min_amount', 19, 4);
            $table->decimal('max_amount', 19, 4);
            $table->unsignedSmallInteger('level');
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'min_amount', 'max_amount', 'level'], 'approval_matrices_band_level_unique');
            $table->index('company_id');
            $table->index('document_type');
            $table->index('approver_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_matrices');
    }
};

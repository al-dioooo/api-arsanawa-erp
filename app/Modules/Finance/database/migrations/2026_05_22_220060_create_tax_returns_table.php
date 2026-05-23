<?php

// Finance module — tax returns (company-scoped periodic VAT/withholding returns)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('tax_type'); // vat, withholding
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft'); // draft, finalized
            $table->decimal('total_output', 19, 4)->default(0);
            $table->decimal('total_input', 19, 4)->default(0);
            $table->decimal('total_payable', 19, 4);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'tax_type', 'period_start', 'period_end']);
            $table->index('company_id');
            $table->index('tax_type');
            $table->index('status');
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_returns');
    }
};

<?php

// Inventory — goods receipt document headers (Penerimaan Bahan Baku / STB)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number'); // No. STB
            $table->string('delivery_note_number')->nullable(); // No. SJ (supplier delivery note)
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete(); // supplier
            $table->date('receipt_date');
            $table->string('status')->default('received'); // received, completed, void
            $table->decimal('total_cost', 19, 4)->default(0);
            $table->text('notes')->nullable();
            // Optional forward link to a Finance vendor bill once AP matching lands.
            // Kept as a plain nullable column (no cross-module FK) to avoid Inventory→Finance coupling.
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'receipt_number']);
            $table->index('company_id');
            $table->index('partner_id');
            $table->index('branch_id');
            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};

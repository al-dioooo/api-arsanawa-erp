<?php

// POS module — sales headers (counter sales and catering orders)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->nullable()->constrained('registers')->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->string('sale_number');
            $table->string('type');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('status')->default('draft');
            $table->date('order_date');
            $table->date('fulfilment_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('amount_paid', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('revenue_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('cogs_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'sale_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['company_id', 'type', 'order_date']);
            $table->index('cashier_shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

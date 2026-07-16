<?php

// Finance module — composite indexes for status + date list/dashboard filters.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['company_id', 'status', 'invoice_date'], 'invoices_status_date_idx');
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->index(['company_id', 'status', 'bill_date'], 'bills_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_status_date_idx');
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->dropIndex('bills_status_date_idx');
        });
    }
};

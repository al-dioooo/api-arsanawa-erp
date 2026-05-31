<?php

// Inventory module — catering batch metadata on stock lots

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->date('production_date')->nullable()->after('expiry_date');
            $table->jsonb('batch_metadata')->nullable()->after('production_date');
            $table->index('production_date');
        });
    }

    public function down(): void
    {
        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->dropIndex(['production_date']);
            $table->dropColumn(['production_date', 'batch_metadata']);
        });
    }
};

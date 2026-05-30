<?php

// POS module — external source metadata for API-key-created catering orders

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('source')->default('internal')->after('status');
            $table->string('source_channel')->nullable()->after('source');
            $table->string('external_reference')->nullable()->after('source_channel');
            $table->foreignId('external_api_key_id')->nullable()->after('external_reference')->constrained('external_api_keys')->nullOnDelete();

            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'source_channel']);
            $table->index('external_api_key_id');
        });

        DB::statement('CREATE UNIQUE INDEX sales_company_source_channel_external_reference_unique ON sales (company_id, source_channel, external_reference) WHERE external_reference IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sales_company_source_channel_external_reference_unique');

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['external_api_key_id']);
            $table->dropIndex(['company_id', 'source']);
            $table->dropIndex(['company_id', 'source_channel']);
            $table->dropIndex(['external_api_key_id']);
            $table->dropColumn([
                'source',
                'source_channel',
                'external_reference',
                'external_api_key_id',
            ]);
        });
    }
};

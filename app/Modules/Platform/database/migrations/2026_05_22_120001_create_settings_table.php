<?php

// Platform module — settings migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module');
            $table->string('key');
            $table->jsonb('value')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'module']);
        });

        // A plain composite unique would not constrain rows where branch_id IS NULL
        // (NULLs compare distinct), so company-level and branch-level uniqueness each
        // need their own partial index. Valid on both PostgreSQL and SQLite.
        DB::statement('CREATE UNIQUE INDEX settings_company_module_key_unique ON settings (company_id, module, key) WHERE branch_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX settings_company_branch_module_key_unique ON settings (company_id, branch_id, module, key) WHERE branch_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

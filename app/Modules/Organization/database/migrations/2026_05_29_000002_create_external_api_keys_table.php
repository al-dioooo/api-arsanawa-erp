<?php

// Organization module — external API keys for company-scoped integrations

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source_channel');
            $table->string('token_prefix', 16);
            $table->string('token_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'revoked_at']);
            $table->index(['company_id', 'source_channel']);
            $table->index('token_prefix');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_api_keys');
    }
};

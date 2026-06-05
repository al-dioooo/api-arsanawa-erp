<?php

// Platform module — WhatsApp message log migration

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('to');
            $table->text('body');

            // Polymorphic link to the source record (e.g. a POS Sale).
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            // pending | sent | failed | skipped
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};

<?php

// Finance module — approval actions (history of decisions on approval requests)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action'); // approved, rejected
            $table->text('remark')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index('approval_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};

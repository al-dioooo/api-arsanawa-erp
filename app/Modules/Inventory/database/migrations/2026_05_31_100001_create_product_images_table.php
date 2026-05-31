<?php

// Inventory module — product and product-unit image metadata

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->morphs('imageable');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('url', 2048);
            $table->string('original_url', 2048)->nullable();
            $table->string('alt_text')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'imageable_type', 'imageable_id']);
            $table->index(['company_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32);
            $table->string('path', 700);
            $table->string('filename');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 127)->index();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title')->nullable();
            $table->string('alt', 1000)->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->char('checksum', 64)->nullable()->index();
            // Derivatives (web/webp/avif/thumb) and extra image info.
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_system', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_system', 'source_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};

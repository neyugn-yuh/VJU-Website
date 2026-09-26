<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_migration_map', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 32);
            $table->string('source_id', 64);
            $table->string('source_parent_id', 64)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('target_type', 32)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->string('target_url', 1000)->nullable();
            // success | skipped | failed
            $table->string('status', 16);
            $table->char('checksum', 64)->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });

        Schema::create('wp_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('source', 16);
            $table->boolean('dry_run')->default(false);
            $table->json('options')->nullable();
            $table->json('stats')->nullable();
            // running | completed | failed
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['type', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_migration_runs');
        Schema::dropIfExists('wp_migration_map');
    }
};

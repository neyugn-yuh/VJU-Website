<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('body');
            $table->string('status', 16)->default('pending');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source_system', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->timestamps();

            $table->index(['content_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->unique(['source_system', 'source_id']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location', 64);
            $table->string('locale', 5);
            $table->string('source_system', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['location', 'locale']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('item_type', 20);
            $table->foreignId('content_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 1000)->nullable();
            $table->string('target', 16)->nullable();
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            // Normalized path, see RedirectResolver::normalize().
            $table->string('old_url', 700)->unique();
            $table->string('new_url', 1000)->nullable();
            // 301, 302, or 410 (intentionally retired).
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('source', 16)->default('manual');
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['redirects', 'audit_logs', 'menu_items', 'menus', 'comments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

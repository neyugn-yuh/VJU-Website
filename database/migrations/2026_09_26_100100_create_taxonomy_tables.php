<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug')->index();
            $table->integer('sort_order')->default(0);
            $table->string('source_system', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id']);
        });

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug');
            // URL path without locale prefix, e.g. "news-vn/dao-tao". Derived from the parent chain.
            $table->string('path', 700);
            $table->timestamps();

            $table->unique(['category_id', 'locale']);
            $table->unique(['locale', 'path']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->index();
            $table->string('source_system', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id']);
        });

        Schema::create('tag_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['tag_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
    }
};

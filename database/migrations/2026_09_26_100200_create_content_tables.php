<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->foreignId('parent_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_commentable')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('template', 32)->nullable();
            // Type-specific structured fields (documents, tuition fees, opportunities...).
            $table->json('fields')->nullable();
            $table->integer('menu_order')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('source_system', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('author_id');
            $table->index(['type', 'status', 'published_at']);
            $table->index(['status', 'scheduled_at']);
            $table->unique(['source_system', 'source_id']);
        });

        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 500);
            $table->string('slug');
            // URL path without locale prefix, e.g. "tuyen-sinh/dai-hoc" or "tuition-fees/slug".
            $table->string('path', 700);
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            // Page content modules (hero, cards, FAQ, documents...) rendered by the public site.
            $table->json('blocks')->nullable();
            $table->timestamps();

            $table->unique(['content_id', 'locale']);
            $table->unique(['locale', 'path']);
            $table->index(['locale', 'slug']);
        });

        // ngram parser: Japanese has no word delimiters; it also handles Vietnamese syllables.
        DB::statement('ALTER TABLE content_translations ADD FULLTEXT content_translations_search (title, excerpt, body) WITH PARSER ngram');

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('meta_title', 500)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->string('og_title', 500)->nullable();
            $table->text('og_description')->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->timestamps();

            $table->unique(['content_id', 'locale']);
        });

        Schema::create('content_category', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['content_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('content_tag', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['content_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['content_id', 'version']);
        });

        Schema::create('content_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['content_id', 'user_id']);
        });

        Schema::create('content_views_daily', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->primary(['content_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        foreach (['content_views_daily', 'content_drafts', 'content_revisions', 'content_tag', 'content_category', 'seo_meta', 'content_translations', 'contents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_posts', function (Blueprint $table): void {
            // Primary Key
            $table->id();

            // Basic Post Information
            $table->string('title')->nullable()->comment('Main title of the post');
            $table->string('subtitle')->nullable()->comment('Optional subtitle for the post');
            $table->string('slug')->nullable()->comment('URL-friendly version of the title');
            $table->string('type')->default('post')->index()->comment('Type: post, page, category, tag');
            $table->string('template', 100)->nullable()->comment('Custom template name (e.g., "landing" for page-landing.twig)');
            $table->string('format')->nullable()->comment('Post format: standard, video, gallery, etc.');

            // Content Fields
            $table->text('excerpt')->nullable()->comment('Short summary/description of the post');
            $table->longText('content')->nullable()->comment('Main content body of the post');
            $table->longText('css')->nullable()->comment('Custom CSS styles for this post');
            $table->longText('js')->nullable()->comment('Custom JavaScript code for this post');

            // Relationships - Using foreignId with proper constraints
            $table->foreignId('category_id')->nullable()->comment('Primary category')->constrained('cms_posts')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->comment('Post author')->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->comment('Parent post ID for hierarchical posts')->constrained('cms_posts')->nullOnDelete();

            // Publication & Status - Using Enums
            $table->string('status', 32)
                ->default('draft')
                ->index()
                ->comment('Publication status (config-driven, no ENUM)');
            $table->enum('visibility', ['public', 'private', 'password'])
                ->default('public')
                ->comment('Visibility level');
            $table->string('post_password', 255)->nullable()->comment('Hashed password for password-protected posts');
            $table->string('password_hint', 255)->nullable()->comment('Optional hint shown on password form');
            $table->timestamp('published_at')->nullable()->index()->comment('Publication date (past=published, future=scheduled, null=draft)');

            // Media & Assets
            $table->foreignId('feature_image_id')->nullable()->comment('Featured image')->constrained('media')->nullOnDelete();

            // Comments & Interaction
            $table->enum('comment_status', ['open', 'closed', 'disabled'])
                ->default('open')
                ->comment('Comment status');
            $table->unsignedBigInteger('hits')->default(0)->comment('View count');

            // SEO Meta Fields - Grouped into JSON
            $table->json('seo_data')->nullable()->comment('SEO metadata: title, description, keywords, robots, canonical');

            // Open Graph Meta Fields - Grouped into JSON
            $table->json('og_data')->nullable()->comment('Open Graph metadata: title, description, image, url');

            // Structured Data
            $table->longText('schema')->nullable()->comment('JSON-LD structured data markup');

            // Flexible Metadata
            $table->json('metadata')->nullable()->comment('Flexible post metadata/properties (reading_time, word_count, custom_fields, etc.)');

            // Performance & Caching
            $table->boolean('is_cached')->default(true)->comment('Whether to enable caching for this post');
            $table->boolean('is_featured')->default(false)->index()->comment('Featured/sticky post flag');

            // Audit Fields
            $table->foreignId('created_by')->nullable()->comment('User who created this post')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who last updated this post')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->comment('User who soft-deleted this post')->constrained('users')->nullOnDelete();

            // Timestamps
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete timestamp');

            // Composite Indexes for Performance
            $table->index(['status', 'published_at'], 'status_published_idx');
            $table->index(['author_id', 'created_at'], 'author_created_idx');
            $table->index(['type', 'status'], 'type_status_idx');
            $table->fullText(['title', 'excerpt', 'content'], 'post_search_idx');
            $table->unique(['slug', 'deleted_at'], 'cms_posts_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_posts');
    }
};

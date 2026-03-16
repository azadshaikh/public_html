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
        Schema::create('cms_post_terms', function (Blueprint $table): void {
            // Primary Key
            $table->id();

            // Relationship Fields - Using foreignId
            $table->foreignId('post_id')
                ->comment('ID of the post')
                ->constrained('cms_posts')
                ->cascadeOnDelete();

            $table->foreignId('term_id')
                ->comment('ID of the term (category or tag)')
                ->constrained('cms_posts')
                ->cascadeOnDelete();

            $table->enum('term_type', ['category', 'tag', 'custom'])
                ->default('category')
                ->comment('Type of term');

            // Additional Fields
            $table->unsignedInteger('sort_order')->default(0)->comment('Manual sorting order');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['post_id', 'term_type'], 'post_term_type_idx');
            $table->index(['term_id', 'term_type'], 'term_post_type_idx');
            $table->unique(['post_id', 'term_id', 'term_type'], 'post_term_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_post_terms');
    }
};

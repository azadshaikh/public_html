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
        Schema::create('movies', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('synopsis');
            $table->text('notes')->nullable();
            $table->string('director');
            $table->string('studio')->nullable();
            $table->string('status', 20)->index();
            $table->string('rating', 20)->index();
            $table->string('language', 10)->nullable();
            $table->date('release_date')->nullable()->index();
            $table->time('release_time')->nullable();
            $table->smallInteger('runtime_minutes')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->decimal('box_office', 14, 2)->nullable();
            $table->decimal('ticket_price', 8, 2)->nullable();
            $table->smallInteger('metascore')->nullable();
            $table->smallInteger('audience_score')->nullable();
            $table->decimal('imdb_rating', 3, 1)->nullable();
            $table->string('trailer_url')->nullable();
            $table->string('official_site')->nullable();
            $table->json('genres')->nullable();
            $table->json('spoken_languages')->nullable();
            $table->json('available_formats')->nullable();
            $table->json('streaming_platforms')->nullable();
            $table->json('content_warnings')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_now_showing')->default(false)->index();
            $table->boolean('has_post_credit_scene')->default(false);
            $table->boolean('is_family_friendly')->default(false);
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};

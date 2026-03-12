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
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->morphs('noteable');
            $table->string('type', 20)->default('note');
            $table->string('visibility', 20)->default('team');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('pinned_by')->nullable();
            $table->json('metadata')->nullable();
            $table->longText('content')->nullable();
            $table->integer('created_by')->default(1);
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->integer('deleted_by')->nullable();
            $table->softDeletes();

            // Indexes for performance
            $table->index('type');
            $table->index('visibility');
            $table->index('is_pinned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};

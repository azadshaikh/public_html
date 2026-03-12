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
        Schema::create('notifications', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->string('category', 50)->default('system');
            $table->string('priority', 20)->default('medium');
            $table->string('title')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient filtering
            $table->index(['notifiable_type', 'notifiable_id', 'category'], 'notifications_notifiable_category_idx');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
            $table->index('category', 'notifications_category_idx');
            $table->index('priority', 'notifications_priority_idx');
            $table->index('created_at', 'notifications_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

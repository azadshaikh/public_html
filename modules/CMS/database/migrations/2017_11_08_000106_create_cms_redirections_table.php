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
        Schema::create('cms_redirections', function (Blueprint $table): void {
            $table->id();
            $table->integer('redirect_type')->default(301)->index('redirect_type');
            $table->string('url_type', 50)->default('internal');
            $table->enum('match_type', ['exact', 'wildcard', 'regex'])
                ->default('exact')
                ->comment('URL matching type: exact, wildcard (e.g., /blog/*), or regex');
            $table->string('source_url', 512)->comment('Source URL to redirect from');
            $table->string('target_url', 1024)->comment('Target URL to redirect to');
            $table->integer('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable()->comment('When the redirect was last triggered');
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable()->comment('Internal notes for admin documentation');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('expires_at')->nullable()->comment('Auto-disable redirect after this date');
            $table->foreignId('created_by')->nullable()->comment('User who created this redirect')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who last updated this redirect')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->comment('User who soft-deleted this redirect')->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index(['match_type', 'status'], 'cms_redirections_match_status_idx');
            $table->index('expires_at', 'cms_redirections_expires_idx');
            $table->unique(['source_url', 'deleted_at'], 'cms_redirections_source_url_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_redirections');
    }
};

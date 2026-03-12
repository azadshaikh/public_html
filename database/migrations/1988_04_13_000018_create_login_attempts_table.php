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
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address', 45)->index();
            $table->string('user_agent')->nullable();
            $table->enum('status', ['success', 'failed', 'blocked', 'cleared'])->default('failed');
            $table->string('failure_reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Composite index for rate limiting queries
            $table->index(['ip_address', 'created_at']);
            $table->index(['email', 'created_at']);

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};

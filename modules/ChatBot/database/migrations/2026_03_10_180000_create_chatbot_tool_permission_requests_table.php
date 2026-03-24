<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_tool_permission_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('conversation_id', 36)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tool_name', 100)->index();
            $table->string('tool_invocation_id', 36)->nullable()->index();
            $table->string('request_fingerprint', 64)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->json('arguments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'tool_name', 'request_fingerprint'], 'chatbot_tool_permission_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_tool_permission_requests');
    }
};

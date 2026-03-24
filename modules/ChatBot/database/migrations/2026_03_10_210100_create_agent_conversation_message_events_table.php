<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_conversation_message_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('message_id', 36)->index();
            $table->string('type', 50)->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'message_id', 'type'], 'agent_conv_msg_events_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversation_message_events');
    }
};

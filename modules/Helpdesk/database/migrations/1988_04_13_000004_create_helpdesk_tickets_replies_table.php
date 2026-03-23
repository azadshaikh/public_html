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
        Schema::create('helpdesk_tickets_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->onDelete('cascade');
            $table->longText('content')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_internal')->default(false);

            $table->foreignId('reply_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_tickets_replies');
    }
};

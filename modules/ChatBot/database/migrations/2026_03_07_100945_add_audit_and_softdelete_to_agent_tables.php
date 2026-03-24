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
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('title');
            $table->unsignedBigInteger('created_by')->nullable()->after('user_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
            $table->softDeletes();
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->after('user_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->dropColumn(['metadata', 'created_by', 'updated_by', 'deleted_by', 'deleted_at']);
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table): void {
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by', 'deleted_at']);
        });
    }
};

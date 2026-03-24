<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_conversation_messages', function (Blueprint $table): void {
            $table->string('status', 30)->default('completed')->index()->after('role');
            $table->string('finish_reason', 50)->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('meta');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('interrupted_at')->nullable()->after('completed_at');
            $table->string('error_code', 100)->nullable()->after('interrupted_at');
            $table->text('error_message')->nullable()->after('error_code');
        });

        DB::table('agent_conversation_messages')
            ->whereNull('started_at')
            ->update([
                'status' => 'completed',
                'started_at' => DB::raw('created_at'),
                'completed_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('agent_conversation_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'finish_reason',
                'started_at',
                'completed_at',
                'interrupted_at',
                'error_code',
                'error_message',
            ]);
        });
    }
};

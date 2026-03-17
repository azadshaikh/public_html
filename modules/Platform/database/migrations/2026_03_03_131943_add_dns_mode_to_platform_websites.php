<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds dns_mode denormalized copy from domain record for fast orchestrator
     * access without joining platform_domains. Set during website creation
     * from the Agency payload.
     */
    public function up(): void
    {
        Schema::table('platform_websites', function (Blueprint $table): void {
            // 'managed' | 'external' | 'subdomain' (copied from domain record)
            $table->string('dns_mode', 30)->nullable()->after('domain');

            $table->index('dns_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_websites', function (Blueprint $table): void {
            $table->dropIndex(['dns_mode']);
            $table->dropColumn('dns_mode');
        });
    }
};

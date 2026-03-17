<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds acme.sh setup tracking columns to know which servers have been
     * prepared for automated SSL certificate issuance via acme.sh.
     */
    public function up(): void
    {
        Schema::table('platform_servers', function (Blueprint $table): void {
            // Whether platform:server:setup-acme has been run on this server
            $table->boolean('acme_configured')->default(false)->after('scripts_updated_at');

            // Email registered with the LE account on this server
            // Format: <server-slug>@astero.net.in (e.g. hestia-sg1@astero.net.in)
            $table->string('acme_email')->nullable()->after('acme_configured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_servers', function (Blueprint $table): void {
            $table->dropColumn(['acme_configured', 'acme_email']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds DNS mode, DNS status, SSL status, and acme_server_id tracking columns
     * to support automated SSL issuance and DNS verification workflows.
     */
    public function up(): void
    {
        Schema::table('platform_domains', function (Blueprint $table): void {
            // DNS mode: how this domain's DNS is managed
            // 'managed' = NS delegation to Bunny, 'external' = customer keeps DNS, 'subdomain' = agency subdomain
            $table->string('dns_mode', 30)->nullable()->after('dns_zone_id');

            // DNS verification status for the provisioning pipeline
            $table->string('dns_status', 30)->default('pending')->after('dns_mode');

            // When DNS was verified (NS propagation confirmed or CNAME records found)
            $table->timestamp('dns_verified_at')->nullable()->after('dns_status');

            // SSL lifecycle status
            $table->string('ssl_status', 30)->default('pending')->after('dns_verified_at');

            // Whether SSL auto-renewal is enabled for this domain
            $table->boolean('ssl_auto_renew')->default(true)->after('ssl_status');

            // The server where acme.sh state for this domain lives.
            // Set on first issuance; renewal SSHes here, not to website.server_id.
            // If a website migrates servers, the original issuing server still
            // holds the acme.sh account + cert history needed for renewal.
            $table->foreignId('acme_server_id')->nullable()->after('ssl_auto_renew')
                ->constrained('platform_servers')->nullOnDelete();

            // Indexes for efficient querying
            $table->index('dns_mode');
            $table->index('dns_status');
            $table->index('ssl_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_domains', function (Blueprint $table): void {
            $table->dropForeign(['acme_server_id']);
            $table->dropIndex(['dns_mode']);
            $table->dropIndex(['dns_status']);
            $table->dropIndex(['ssl_status']);
            $table->dropColumn([
                'dns_mode',
                'dns_status',
                'dns_verified_at',
                'ssl_status',
                'ssl_auto_renew',
                'acme_server_id',
            ]);
        });
    }
};

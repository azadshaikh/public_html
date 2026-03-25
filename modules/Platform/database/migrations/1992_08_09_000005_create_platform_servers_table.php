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
        Schema::create('platform_servers', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Basic information
            $table->string('uid', 25)->nullable()->comment('Custom unique identifier');
            $table->string('name', 155)->nullable();
            $table->string('type')->nullable()->default('default');
            $table->string('driver')->nullable()->default('hestia');

            // Network configuration
            $table->string('ip', 45)->nullable();
            $table->integer('port')->nullable();
            $table->longText('fqdn')->nullable();

            // Authentication
            $table->text('access_key_id')->nullable()->comment('API access key ID (encrypted)');
            $table->text('access_key_secret')->nullable()->comment('API access key secret (encrypted)');

            // SSH Configuration for remote access
            $table->text('ssh_public_key')->nullable()->comment('Encrypted SSH public key');
            $table->unsignedSmallInteger('ssh_port')->default(22)->comment('SSH port');
            $table->string('ssh_user', 50)->default('root')->comment('SSH username');

            // Capacity management
            $table->integer('current_domains')->default(0)->comment('Current domain count on server');
            $table->integer('max_domains')->nullable()->comment('Maximum domain capacity (soft limit)');

            // Monitoring
            $table->boolean('monitor')->default(false);

            // Status and metadata
            $table->string('status')->default('active');
            $table->string('provisioning_status', 30)->default('pending')->comment('pending|provisioning|ready|failed');
            $table->string('scripts_version', 50)->nullable()->comment('Deployed Astero scripts version');
            $table->timestamp('scripts_updated_at')->nullable()->comment('When scripts were last updated');
            $table->boolean('acme_configured')->default(false);
            $table->string('acme_email')->nullable();
            $table->json('metadata')->nullable()->comment('Server specs: cpu, ram, storage, os, versions');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uid');
            $table->index('ip');
            $table->index('status');
            $table->index('provisioning_status');
            $table->index('type');
            $table->index('current_domains');
            $table->index('max_domains');

            // Foreign key constraints for audit fields
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        // Deferred FK: platform_domains.acme_server_id → platform_servers (domains created before servers)
        Schema::table('platform_domains', function (Blueprint $table): void {
            $table->foreign('acme_server_id')->references('id')->on('platform_servers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_domains', function (Blueprint $table): void {
            $table->dropForeign(['acme_server_id']);
        });

        Schema::dropIfExists('platform_servers');
    }
};

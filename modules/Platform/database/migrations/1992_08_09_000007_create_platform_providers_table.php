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
        Schema::create('platform_providers', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Basic information
            $table->string('name');
            $table->string('type')->comment('dns, cdn, server, domain_registrar - see config/provider.php');
            $table->string('vendor')->default('manual')->comment('bunny, cloudflare, hetzner, etc. - see config/provider.php');
            $table->string('email')->nullable()->comment('Account email for identification');

            // Authentication and configuration
            $table->text('credentials')->nullable()->comment('Encrypted JSON - API keys, tokens, etc.');
            $table->json('metadata')->nullable()->comment('Balance, stats, vendor-specific settings');

            // Status
            $table->string('status')->default('active')->comment('active, inactive, suspended - see config/provider.php');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('vendor');
            $table->index('status');
            $table->index(['type', 'vendor']);
            $table->index(['type', 'status']);

            // Foreign key constraints for audit fields
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_providers');
    }
};

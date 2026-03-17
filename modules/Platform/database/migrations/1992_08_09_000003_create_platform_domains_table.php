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
        Schema::create('platform_domains', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Domain information
            $table->string('name', 255)->nullable();
            $table->foreignId('tld_id')->nullable()->constrained('platform_tlds')->cascadeOnUpdate()->nullOnDelete();
            $table->string('type')->nullable();

            // Ownership
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('platform_agencies')->nullOnDelete();

            // DNS configuration
            $table->string('dns_provider', 255)->nullable()->comment('Cloudflare, Bunny, etc.');
            $table->string('dns_zone_id', 255)->nullable();
            $table->string('name_server_1', 255)->nullable();
            $table->string('name_server_2', 255)->nullable();
            $table->string('name_server_3', 255)->nullable();
            $table->string('name_server_4', 255)->nullable();

            // Registrar information
            $table->string('registrar_name', 255)->nullable()->comment('Name of the registrar');
            $table->date('registered_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('updated_date')->nullable();

            // Status and metadata
            $table->string('status')->default('active');
            $table->json('metadata')->nullable()->comment('Flexible metadata for registrar or DNS settings');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('tld_id');
            $table->index('customer_id');
            $table->index('agency_id');
            $table->index('status');
            $table->index('expiry_date');

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
        Schema::dropIfExists('platform_domains');
    }
};

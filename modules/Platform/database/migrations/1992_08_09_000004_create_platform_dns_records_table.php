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
        Schema::create('platform_dns_records', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Relationships
            $table->foreignId('domain_id')->nullable()->constrained('platform_domains')->nullOnDelete();

            // Provider identifiers
            $table->string('zone_id')->nullable()->comment('DNS zone ID from provider');
            $table->string('record_id')->nullable()->comment('Record ID from provider');

            // DNS record data
            $table->tinyInteger('type')->default(0)->nullable()->comment('DNS record type (A, AAAA, CNAME, MX, TXT, etc.)');
            $table->string('name')->nullable()->comment('Record name/host');
            $table->text('value')->nullable()->comment('Record value/content');
            $table->unsignedInteger('ttl')->default(3600)->nullable()->comment('Time to live in seconds');

            // Common record parameters
            $table->unsignedSmallInteger('priority')->nullable()->comment('Priority for MX/SRV records');
            $table->unsignedSmallInteger('weight')->nullable()->comment('Weight for SRV records');
            $table->unsignedSmallInteger('port')->nullable()->comment('Port for SRV records');

            // Status
            $table->boolean('disabled')->default(false)->nullable();

            // Provider-specific data
            $table->json('metadata')->nullable()->comment('Provider-specific attributes (e.g., Bunny pullzone_id, acceleration, geo-routing)');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('domain_id');
            $table->index('zone_id');
            $table->index('type');
            $table->index('name');

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
        Schema::dropIfExists('platform_dns_records');
    }
};

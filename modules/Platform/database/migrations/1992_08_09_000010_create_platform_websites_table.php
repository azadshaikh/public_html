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
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('platform_websites', function (Blueprint $table) use ($driver): void {
            // Primary key
            $table->id();

            // Basic information
            $table->string('uid', 25)->nullable()->comment('Custom unique identifier');
            $table->string('name')->nullable();
            $table->string('type')->default('trial')->nullable();
            $table->json('niches')->nullable()->comment('Array of niche/industry identifiers');

            // Domain configuration
            $table->foreignId('domain_id')->nullable()->constrained('platform_domains')->nullOnDelete();
            $table->string('domain')->nullable();
            $table->foreignId('ssl_secret_id')->nullable()->constrained('platform_secrets')->nullOnDelete();

            // Infrastructure
            $table->foreignId('server_id')->nullable()->constrained('platform_servers')->nullOnDelete();
            $table->string('astero_version')->nullable();

            // Ownership
            $table->foreignId('agency_id')->nullable()->constrained('platform_agencies')->nullOnDelete();

            // Security - Platform-to-Website authentication token
            $table->longText('secret_key')->nullable()->comment('Encrypted random token (64 chars) for platform API calls to provisioned website. Generated via Str::random(64), stored encrypted, sent plain to client .env');

            // Status and expiration
            $table->string('status')->default('pending');
            $table->string('plan_tier')->nullable()->comment('Website plan tier (basic, premium, business, enterprise)');
            $table->string('customer_ref')->nullable()->comment('Agency customer ID for lookup/transfer queries');
            $table->json('customer_data')->nullable()->comment('Customer snapshot: {email, name, company, phone}');
            $table->string('plan_ref')->nullable()->comment('Agency plan ID/slug for filtering queries');
            $table->json('plan_data')->nullable()->comment('Plan snapshot: {name, quotas: {storage_mb, bandwidth_mb}, features: {...}}');
            $table->timestamp('expired_on')->nullable();

            // Metadata
            $table->json('metadata')->nullable()->comment('Provisioning config, flags, etc.');

            /*
             * VIRTUAL COLUMN PATTERN: is_agency_flag
             *
             * Purpose: Enforce database-level unique constraint on a JSON field value
             *
             * Architecture:
             * - metadata.is_agency (JSON) - Actual data stored and accessed via model accessor
             * - is_agency_flag (virtual column) - Computed column used ONLY for unique constraint
             *
             * Why this pattern?
             * - MySQL cannot create unique constraints on JSON fields directly
             * - Virtual column extracts JSON value and converts to 1 (true) or NULL (false/not set)
             * - NULL values are ignored in unique constraints (SQL standard behavior)
             * - This allows the constraint to enforce "only ONE agency website per agency"
             *   while ignoring non-agency websites
             *
             * Usage:
             * - DO: Use $website->is_agency accessor/mutator to read/write the value
             * - DO: Use scopeIsAgencyWebsite() query scope for filtering
             * - DON'T: Access is_agency_flag directly - it's a database implementation detail
             * - DON'T: Include is_agency_flag in $fillable or select queries
             *
             * Example:
             * $website->is_agency = true;  // Sets metadata.is_agency, is_agency_flag auto-updates
             * Website::isAgencyWebsite()->get();  // Query scope for filtering
             */
            $isAgencyFlag = $table->boolean('is_agency_flag')->nullable();

            if ($driver === 'pgsql') {
                $isAgencyFlag
                    ->storedAs("(CASE WHEN (metadata->>'is_agency') = 'true' THEN TRUE ELSE NULL END)")
                    ->comment('Generated column for unique constraint on metadata.is_agency (PostgreSQL) - DO NOT access directly');
            } else {
                $isAgencyFlag
                    ->virtualAs("CASE WHEN JSON_EXTRACT(metadata, '$.is_agency') = true THEN 1 ELSE NULL END")
                    ->comment('Virtual column for unique constraint on metadata.is_agency (MySQL/MariaDB) - DO NOT access directly');
            }

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uid');
            $table->index('domain');
            $table->index('status');
            $table->index('type');
            $table->index('plan_tier');
            $table->index('agency_id');
            $table->index(['agency_id', 'customer_ref'], 'platform_websites_agency_customer_ref_index');
            $table->index(['agency_id', 'plan_ref'], 'platform_websites_agency_plan_ref_index');
            $table->index('server_id');
            $table->index('expired_on');

            // Unique constraint: only ONE website per agency can have is_agency=true
            $table->unique(['agency_id', 'is_agency_flag'], 'unique_agency_website');

            // Foreign key constraints for audit fields
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('platform_agencies', function (Blueprint $table): void {
            $table->foreign('agency_website_id')
                ->references('id')
                ->on('platform_websites')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_agencies', function (Blueprint $table): void {
            $table->dropForeign(['agency_website_id']);
        });

        Schema::dropIfExists('platform_websites');
    }
};

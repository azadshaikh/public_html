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
        Schema::create('platform_agencies', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Basic information
            $table->string('uid', 25)->nullable()->comment('Custom unique identifier');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('type')->nullable()->default('default');
            $table->string('plan')->nullable()->default('starter')->comment('Agency partnership plan (starter, growth, reseller, custom)');
            $table->string('website_id_prefix', 10)->default('WS')->comment('Agency-specific prefix used for generated platform website IDs');
            $table->unsignedSmallInteger('website_id_zero_padding')->default(5)->comment('Number of leading zeros used for generated platform website IDs');

            // Ownership
            $table->foreignId('owner_id')->comment('Agency owner')->constrained('users');

            // Status
            $table->string('status')->default('active');

            // Metadata (branding assets and other settings)
            $table->json('metadata')->nullable()->comment('Flexible metadata for branding assets (branding_name, branding_logo, branding_icon, branding_website) and other settings');

            // Agency website reference (FK constraint added in later migration after websites table exists)
            $table->unsignedBigInteger('agency_website_id')->nullable()
                ->comment("Reference to the agency's own SaaS platform website (only one per agency)");

            // Agency-to-Platform authentication token
            $table->longText('secret_key')->nullable()
                ->comment('Encrypted random token (64 chars) for agency-to-platform API authentication. Generated via Str::random(64), sent plain to agency website .env as AGY_SECRETKEY');
            $table->string('webhook_url', 500)->nullable();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uid');
            $table->index('owner_id');
            $table->index('status');
            $table->index('plan');

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
        Schema::dropIfExists('platform_agencies');
    }
};

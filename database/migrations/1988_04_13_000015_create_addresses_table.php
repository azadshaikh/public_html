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
        Schema::create('addresses', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Polymorphic relationship
            $table->morphs('addressable'); // addressable_id, addressable_type

            // Personal information
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();

            // Address components
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable(); // Landmark or additional address details

            // Geographic location
            $table->string('city')->nullable();
            $table->string('city_code', 10)->nullable(); // City code from API
            $table->string('state')->nullable(); // Full state/province name
            $table->string('state_code', 10)->nullable(); // ISO 3166-2 state code
            $table->string('country')->nullable(); // Full country name
            $table->string('country_code', 2)->nullable(); // ISO2 country code
            $table->string('zip', 20)->nullable();

            // Contact information
            $table->string('phone')->nullable();
            $table->string('phone_code', 10)->nullable(); // Phone country code

            // Geographic coordinates
            $table->decimal('latitude', 13, 9)->nullable();
            $table->decimal('longitude', 14, 9)->nullable();

            // Address metadata
            $table->string('type')->default('primary'); // billing, shipping, home, work, etc.
            $table->json('metadata')->nullable(); // Additional metadata in JSON format
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['addressable_type', 'addressable_id', 'type']);
            $table->index(['country_code', 'state_code']);
            $table->index(['city', 'state_code']);
            $table->index('is_primary');

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
        Schema::dropIfExists('addresses');
    }
};

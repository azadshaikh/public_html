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
        Schema::create('platform_agency_server', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Relationships
            $table->foreignId('agency_id')->constrained('platform_agencies')->onDelete('cascade');
            $table->foreignId('server_id')->constrained('platform_servers')->onDelete('cascade');

            // Pivot attributes
            $table->boolean('is_primary')->default(false)->comment('Primary server for this agency');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->unique(['agency_id', 'server_id'], 'agency_server_unique');
            $table->index(['server_id', 'agency_id']);
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_agency_server');
    }
};

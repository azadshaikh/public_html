<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the pivot table for polymorphic many-to-many relationships
     * between providers and various models (websites, domains, servers, agencies, etc.)
     */
    public function up(): void
    {
        Schema::create('platform_providerables', function (Blueprint $table): void {
            $table->foreignId('provider_id')->constrained('platform_providers')->onDelete('cascade');
            $table->morphs('providerable'); // Creates providerable_type and providerable_id
            $table->boolean('is_primary')->default(false)->comment('Primary provider for this type');

            // Unique constraint to prevent duplicate provider-model associations
            $table->unique(
                ['provider_id', 'providerable_id', 'providerable_type'],
                'provider_unique_link'
            );

            // Index for efficient lookups by model
            $table->index(['providerable_type', 'providerable_id'], 'providerable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_providerables');
    }
};

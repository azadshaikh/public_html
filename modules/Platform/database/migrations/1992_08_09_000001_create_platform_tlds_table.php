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
        Schema::create('platform_tlds', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // TLD information
            $table->string('tld', 63)->nullable();
            $table->string('whois_server', 255)->nullable();
            $table->string('pattern', 255)->nullable();

            // Classification flags
            $table->boolean('is_main')->default(false);
            $table->boolean('is_suggested')->default(false);

            // Pricing
            $table->text('price')->nullable();
            $table->text('sale_price')->nullable();
            $table->mediumText('affiliate_link')->nullable();

            // Display and status
            $table->integer('tld_order')->nullable();
            $table->boolean('status')->default(true);

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible registry-specific options');

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tld');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_tlds');
    }
};

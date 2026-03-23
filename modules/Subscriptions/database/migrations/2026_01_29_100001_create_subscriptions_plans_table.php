<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions_plans', function (Blueprint $table): void {
            $table->id();

            // Plan identification
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Billing configuration
            $table->string('billing_cycle')->default('monthly'); // monthly, quarterly, yearly, lifetime
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Trial and grace periods
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('grace_days')->default(0);

            // Display settings
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible metadata for plan configuration and display');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions_plans');
    }
};

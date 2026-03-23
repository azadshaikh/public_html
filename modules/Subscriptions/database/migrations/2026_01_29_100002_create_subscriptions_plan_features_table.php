<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions_plan_features', function (Blueprint $table): void {
            $table->id();

            // Plan relationship
            $table->foreignId('plan_id')->constrained('subscriptions_plans')->cascadeOnDelete();

            // Feature identification
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();

            // Feature type and value
            $table->string('type')->default('boolean'); // boolean, limit, value, unlimited
            $table->string('value')->nullable(); // true/false for boolean, number for limit, text for value

            // Display order
            $table->unsignedInteger('sort_order')->default(0);

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible metadata for feature configuration');

            $table->timestamps();

            // Indexes
            $table->unique(['plan_id', 'code']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions_plan_features');
    }
};

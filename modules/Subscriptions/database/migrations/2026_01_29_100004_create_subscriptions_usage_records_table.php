<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions_usage_records', function (Blueprint $table): void {
            $table->id();

            // Subscription relationship
            $table->foreignId('subscription_id')->constrained('subscriptions_subscriptions')->cascadeOnDelete();

            // Feature tracking
            $table->string('feature_code');
            $table->integer('quantity')->default(1);
            $table->timestamp('recorded_at');

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible metadata for usage record details');

            $table->timestamps();

            // Indexes
            $table->index(['subscription_id', 'feature_code']);
            $table->index(['subscription_id', 'recorded_at']);
            $table->index('feature_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions_usage_records');
    }
};

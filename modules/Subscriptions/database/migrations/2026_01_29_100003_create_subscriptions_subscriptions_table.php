<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('unique_id')->nullable();

            // Customer relationship (subscriptions are customer-owned)
            $table->foreignId('customer_id')->constrained('customers_customers')->cascadeOnDelete();

            // Plan relationship
            $table->foreignId('plan_id')->constrained('subscriptions_plans')->restrictOnDelete();
            $table->foreignId('previous_plan_id')->nullable()->constrained('subscriptions_plans')->nullOnDelete();

            // Status
            $table->string('status')->default('active'); // active, trialing, past_due, canceled, expired, paused

            // Pricing (snapshot from plan at subscription time)
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Trial period
            $table->timestamp('trial_ends_at')->nullable();

            // Billing period
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // Plan change tracking
            $table->timestamp('plan_changed_at')->nullable();

            // Cancellation
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('cancels_at')->nullable(); // When it will actually end (grace period)
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('ended_at')->nullable();

            // Pausing
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumes_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible metadata for subscription context and external references');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['customer_id', 'status'], 'subs_customer_status_idx');
            $table->index('customer_id', 'subs_customer_idx');
            $table->index('status');
            $table->index('current_period_end');
            $table->index('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions_subscriptions');
    }
};

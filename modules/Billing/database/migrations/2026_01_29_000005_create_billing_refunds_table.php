<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_refunds', function (Blueprint $table): void {
            $table->id();

            // Refund identification
            $table->string('refund_number')->unique();
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->nullable()->unique();

            // Payment relationship
            $table->foreignId('payment_id')->constrained('billing_payments')->cascadeOnDelete();

            // Invoice relationship (for tracking)
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();

            // Customer relationship
            $table->foreignId('customer_id')->nullable()->constrained('customers_customers')->nullOnDelete();

            // Amount
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');

            // Refund type
            $table->string('type')->default('full');

            // Status
            $table->string('status')->default('pending');

            // Gateway response
            $table->string('gateway_refund_id')->nullable()->index();
            $table->json('gateway_response')->nullable();

            // Timestamps
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Reason
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable()->comment('Flexible metadata for refund details');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('customer_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refunds');
    }
};

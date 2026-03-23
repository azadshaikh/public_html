<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();

            // Payment identification
            $table->string('payment_number')->unique();
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->nullable()->unique();

            // Invoice relationship (nullable for advance payments)
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();

            // Customer relationship
            $table->foreignId('customer_id')->nullable()->constrained('customers_customers')->nullOnDelete();

            // Amount
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);

            // Payment method
            $table->string('payment_method')->default('card');
            $table->string('payment_gateway')->default('stripe');

            // Status
            $table->string('status')->default('pending');

            // Gateway response
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->json('gateway_response')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Notes and metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable()->comment('Flexible metadata for payment provider references');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('customer_id');
            $table->index(['status', 'created_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_transactions', function (Blueprint $table): void {
            $table->id();

            // Transaction identification
            $table->string('transaction_id')->unique();
            $table->string('reference')->nullable();

            // Polymorphic relation to source (invoice, payment, refund, credit)
            $table->unsignedBigInteger('transactionable_id');
            $table->string('transactionable_type');

            // Customer relationship
            $table->foreignId('customer_id')->nullable()->constrained('customers_customers')->nullOnDelete();

            // Transaction type
            $table->string('type');

            // Amount
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);

            // Balance tracking
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();

            // Payment gateway
            $table->string('payment_method')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->json('gateway_response')->nullable();

            // Status
            $table->string('status')->default('completed');

            // Description
            $table->text('description')->nullable();
            $table->json('metadata')->nullable()->comment('Flexible metadata for transaction tracking');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
    }
};

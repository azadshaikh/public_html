<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();

            // Invoice identification
            $table->string('invoice_number')->unique();
            $table->string('reference')->nullable();

            // Customer relationship
            $table->foreignId('customer_id')->nullable()->constrained('customers_customers')->nullOnDelete();

            // Billing details
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->text('billing_address')->nullable();

            // Amounts
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);

            // Currency
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);

            // Dates
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            // Status
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('unpaid');

            // Stripe integration
            $table->string('stripe_invoice_id')->nullable()->index();

            // Notes and metadata
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->json('metadata')->nullable()->comment('Flexible metadata for invoice context and external references');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('customer_id');
            $table->index(['status', 'payment_status']);
            $table->index(['due_date', 'payment_status']);
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};

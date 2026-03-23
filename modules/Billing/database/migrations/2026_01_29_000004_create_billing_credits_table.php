<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_credits', function (Blueprint $table): void {
            $table->id();

            // Credit identification
            $table->string('credit_number')->unique();
            $table->string('reference')->nullable();

            // Customer relationship
            $table->foreignId('customer_id')->constrained('customers_customers')->cascadeOnDelete();

            // Related invoice (if credit is from invoice)
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();

            // Amount
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_used', 15, 2)->default(0);
            $table->decimal('amount_remaining', 15, 2);
            $table->string('currency', 3)->default('USD');

            // Credit type
            $table->string('type')->default('credit_note');

            // Status
            $table->string('status')->default('active');

            // Expiration
            $table->date('expires_at')->nullable();

            // Reason
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable()->comment('Flexible metadata for credit adjustments');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('customer_id');
            $table->index(['status', 'amount_remaining']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_credits');
    }
};

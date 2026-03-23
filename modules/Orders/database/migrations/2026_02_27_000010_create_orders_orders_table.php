<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();

            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers_customers')
                ->nullOnDelete();

            // Order type
            $table->string('type')->default('one_time');
            // subscription_signup | subscription_upgrade | addon | one_time

            // Status
            $table->string('status')->default('pending');
            // pending | processing | active | cancelled | refunded

            // Financials
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');

            // Coupon (plain columns, no FK to avoid cross-module dependency)
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code')->nullable();

            // Stripe
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();

            $table->text('notes')->nullable();

            $table->jsonb('metadata')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_orders');
    }
};

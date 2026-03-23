<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_coupon_redemptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('coupon_id')->constrained('billing_coupons')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers_customers')->cascadeOnDelete();

            // order_id references orders_orders (Orders module); stored as plain
            // bigint to avoid cross-module FK dependency at migration time.
            $table->unsignedBigInteger('order_id')->nullable()->index();

            $table->decimal('discount_applied', 10, 2);
            $table->timestamp('redeemed_at');

            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['coupon_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_coupon_redemptions');
    }
};

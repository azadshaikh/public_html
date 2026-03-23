<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_coupon_redemptions', function (Blueprint $table): void {
            $table->unique(['coupon_id', 'order_id'], 'billing_coupon_redemptions_coupon_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('billing_coupon_redemptions', function (Blueprint $table): void {
            $table->dropUnique('billing_coupon_redemptions_coupon_order_unique');
        });
    }
};

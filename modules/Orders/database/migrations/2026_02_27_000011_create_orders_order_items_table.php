<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders_order_items', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('order_id')->index();
            $table->foreign('order_id')
                ->references('id')
                ->on('orders_orders')
                ->cascadeOnDelete();

            // Plan reference (no FK — avoids cross-module dependency)
            $table->unsignedBigInteger('plan_id')->nullable();

            // Values stored at time of order (immutable after creation)
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_order_items');
    }
};

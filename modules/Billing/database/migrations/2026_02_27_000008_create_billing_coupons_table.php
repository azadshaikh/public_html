<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_coupons', function (Blueprint $table): void {
            $table->id();

            // Identification
            $table->string('code', 50)->unique()->comment('Stored uppercase. Case-insensitive at input.');
            $table->string('name');
            $table->text('description')->nullable();

            // Discount configuration
            $table->string('type', 20)->comment('percent | fixed');
            $table->decimal('value', 10, 2)->comment('Percent: 0-100. Fixed: currency amount.');
            $table->string('currency', 3)->nullable()->comment('For fixed type only. Null = any currency.');

            // Duration
            $table->string('discount_duration', 20)->default('once')->comment('once | repeating | forever');
            $table->unsignedSmallInteger('duration_in_months')->nullable()->comment('Used when discount_duration = repeating.');

            // Usage limits
            $table->unsignedInteger('max_uses')->nullable()->comment('Null = unlimited.');
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedTinyInteger('max_uses_per_customer')->default(1);

            // Restrictions
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->json('applicable_plan_ids')->nullable()->comment('Array of subscriptions_plans.id. Null = all plans.');

            // Validity
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Metadata
            $table->json('metadata')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('is_active');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_coupons');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_websites', function (Blueprint $table): void {
            $table->id();

            // Platform-assigned identifier (e.g. "SITE-abc123")
            $table->string('site_id')->unique();

            $table->string('domain');
            $table->string('name')->nullable();
            $table->string('type')->default('paid');
            $table->string('status')->default('provisioning');

            // Owner (local user who ordered this website)
            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('owner_email')->nullable();
            $table->string('owner_name')->nullable();

            // Customer snapshot (mirrors Platform's customer_ref/customer_data pattern)
            $table->string('customer_ref')->nullable();
            $table->json('customer_data')->nullable();

            $table->boolean('is_www')->default(false);
            $table->string('plan')->nullable();

            // Plan snapshot (mirrors Platform's plan_ref/plan_data pattern)
            $table->string('plan_ref')->nullable();
            $table->json('plan_data')->nullable();

            $table->string('server_name')->nullable();
            $table->string('astero_version')->nullable();
            $table->string('admin_slug')->nullable();

            $table->timestamp('expired_on')->nullable();
            $table->timestamp('provisioned_at')->nullable();

            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('status');
            $table->index('type');
            $table->index(['owner_id', 'customer_ref']);
            $table->index(['owner_id', 'plan_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_websites');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers_customers', function (Blueprint $table): void {
            $table->id();

            // Identity
            $table->string('type')->default('company'); // 'person' or 'company'
            $table->string('unique_id')->nullable()->unique(); // CUS00001

            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('company_name')->nullable();
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('logo')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_code', 10)->nullable();

            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('website')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('language', 10)->nullable()->default('en');

            // Business Details
            $table->string('industry', 80)->nullable();
            $table->string('customer_group', 80)->nullable();
            $table->string('org_size')->nullable();
            $table->string('revenue')->nullable();
            $table->text('description')->nullable();

            // Status & Classification
            $table->string('status')->default('active');
            $table->string('source')->nullable();
            $table->string('tier')->default('bronze');
            $table->json('tags')->nullable();

            // Metadata & Preferences
            $table->json('metadata')->nullable()->comment('Flexible metadata for customer-specific fields');
            $table->boolean('opt_in_marketing')->default(false);
            $table->boolean('do_not_call')->default(false);
            $table->boolean('do_not_email')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Timeline
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_action_date')->nullable();

            $table->softDeletes();

            $table->index(['company_name']);
            $table->index(['email']);
            $table->index(['status']);
            $table->index('type');
            $table->index('tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers_customers');
    }
};

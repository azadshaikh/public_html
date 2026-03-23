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
        Schema::create('customers_customer_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers_customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('phone_code', 10)->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('metadata')->nullable()->comment('Flexible metadata for contact-specific fields');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_primary']);
            $table->index(['email']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers_customer_contacts');
    }
};

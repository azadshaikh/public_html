<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_taxes', function (Blueprint $table): void {
            $table->id();

            // Tax identification
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            // Tax rate
            $table->decimal('rate', 5, 2);
            $table->string('type')->default('percentage');

            // Applicability
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();

            // Product/service categories
            $table->json('applies_to')->nullable();
            $table->json('excludes')->nullable();

            // Compound tax (tax on tax)
            $table->boolean('is_compound')->default(false);
            $table->unsignedInteger('priority')->default(0);

            // Status
            $table->boolean('is_active')->default(true);

            // Effective dates
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible metadata for tax configuration');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'country']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_taxes');
    }
};

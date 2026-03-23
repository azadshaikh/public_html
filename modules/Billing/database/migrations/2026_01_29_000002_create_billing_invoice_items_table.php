<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoice_items', function (Blueprint $table): void {
            $table->id();

            // Invoice relationship
            $table->foreignId('invoice_id')->constrained('billing_invoices')->cascadeOnDelete();

            // Item details
            $table->string('name');
            $table->text('description')->nullable();

            // Quantities and pricing
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);

            // Tax
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);

            // Discount
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);

            // Total
            $table->decimal('total', 15, 2)->default(0);

            // Invoiceable item (polymorphic)
            $table->nullableMorphs('invoiceable');

            // Sort order
            $table->unsignedInteger('sort_order')->default(0);

            // Metadata
            $table->json('metadata')->nullable()->comment('Flexible metadata for invoice item details');

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_items');
    }
};

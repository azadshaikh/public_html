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
        Schema::create('email_providers', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Provider information
            $table->string('name');
            $table->text('description')->nullable();

            // Sender configuration
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();

            // SMTP configuration
            $table->string('smtp_host')->nullable();
            $table->string('smtp_user')->nullable();
            $table->string('smtp_password')->nullable();
            $table->string('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();

            // Email settings
            $table->string('reply_to')->nullable();
            $table->string('bcc')->nullable();
            $table->longText('signature')->nullable();

            // Configuration
            $table->enum('status', ['active', 'inactive', 'trash'])->default('active');
            $table->unsignedInteger('order')->default(0);
            $table->json('metadata')->nullable();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('order');

            // Foreign key constraints for audit fields
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_providers');
    }
};

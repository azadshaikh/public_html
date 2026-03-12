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
        Schema::create('email_templates', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Template content
            $table->string('name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('message')->nullable();
            $table->text('send_to')->nullable();

            // Configuration
            $table->unsignedBigInteger('provider_id')->nullable()->default(0);
            $table->boolean('is_raw')->nullable()->default(false);
            $table->enum('status', ['active', 'inactive', 'trash'])->default('active');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

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
        Schema::dropIfExists('email_templates');
    }
};

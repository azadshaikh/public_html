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
        Schema::create('platform_secrets', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Polymorphic relationship
            $table->morphs('secretable'); // secretable_id, secretable_type

            // Secret information
            $table->string('key');
            $table->string('type');
            $table->text('username')->nullable();
            $table->text('value');

            // Status and expiration
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('key');
            $table->index('type');
            $table->index('is_active');

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
        Schema::dropIfExists('platform_secrets');
    }
};

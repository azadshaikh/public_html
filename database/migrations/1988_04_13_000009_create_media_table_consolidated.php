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
        Schema::create('media', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Polymorphic relationship
            $table->morphs('model');

            // File identification
            $table->string('uuid', 36)->nullable()->unique();
            $table->string('media_storage_root')->nullable();
            $table->string('cdn_base_url')->nullable();

            // Media information
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();

            // Content information
            $table->longText('alt_text')->nullable();
            $table->string('caption')->nullable();

            // Storage configuration
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');

            // Media processing data
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');

            // Ordering and status
            $table->unsignedInteger('order_column')->nullable();
            $table->enum('status', ['inactive', 'active', 'trash'])->default('active');

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable()->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('deleted_at', 'media_deleted_at_index');
            $table->index('created_by', 'media_created_by_index');
            $table->index('mime_type', 'media_mime_type_index');
            $table->index('created_at', 'media_created_at_index');
            $table->index('order_column');
            $table->index('status');

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
        Schema::dropIfExists('media');
    }
};

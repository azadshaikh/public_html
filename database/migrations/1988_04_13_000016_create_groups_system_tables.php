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
        // Create groups table
        Schema::create('groups', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Group information
            $table->string('name', 125)->nullable();
            $table->string('slug', 125)->nullable();
            $table->enum('status', ['active', 'inactive', 'trash'])->nullable()->default('active');

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

        // Create group_items table
        Schema::create('group_items', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Relationships
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();

            // Item information
            $table->string('name', 125)->nullable();
            $table->string('slug', 125)->nullable();
            $table->unsignedInteger('ranking')->nullable()->default(0);
            $table->enum('status', ['active', 'inactive', 'trash'])->nullable()->default('active');
            $table->boolean('is_default')->nullable()->default(false);
            $table->json('metadata')->nullable(); // Flexible field for extra data

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('group_id');
            $table->index('parent_id');
            $table->index('status');

            // Foreign key constraints
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('group_items')->onDelete('cascade');

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
        Schema::dropIfExists('group_items');
        Schema::dropIfExists('groups');
    }
};

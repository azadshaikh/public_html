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
        Schema::create('release_manager_releases', function (Blueprint $table): void {
            $table->id();
            $table->enum('release_type', ['application', 'module', 'theme'])->nullable()->default('application');
            $table->enum('version_type', ['major', 'minor', 'patch'])->nullable()->default('patch');
            $table->string('package_identifier', 125)->nullable();
            $table->string('version', 25)->nullable()->comment('unique version number');
            $table->longText('change_log')->nullable();
            $table->string('release_link', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('checksum', 100)->nullable()->comment('SHA256 hash with algorithm prefix');
            $table->unsignedBigInteger('file_size')->nullable()->comment('Size in bytes');
            $table->json('metadata')->nullable();
            $table->timestamp('release_at')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Foreign key constraints for audit fields
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('release_manager_releases');
    }
};

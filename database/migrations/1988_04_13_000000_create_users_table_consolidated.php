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
        Schema::create('users', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Personal information
            $table->string('name'); // Full name - primary display field, auto-synced from first/last
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // Authentication/Identification
            $table->string('username')->nullable()->unique(); // Add unique constraint for username
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Profile information
            $table->string('tagline')->nullable();
            $table->longText('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('group')->nullable(); // Azad: User group classification will implement in future

            // Account status
            $table->string('status')->default('active');

            // Activity tracking
            $table->timestamp('last_access')->nullable();

            // Flexible data
            $table->json('metadata')->nullable(); // Flexible field for extra data including social URLs and birth date

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Laravel authentication
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Notification preferences
            $table->boolean('notifications_enabled')->default(true);
            $table->json('notification_preferences')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('status');
            $table->index('group');
            $table->index('email');

            // Foreign key constraints for audit fields
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');

        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

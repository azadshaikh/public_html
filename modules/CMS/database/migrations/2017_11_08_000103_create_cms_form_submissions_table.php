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
        Schema::create('cms_form_submissions', function (Blueprint $table): void {
            // Primary Key
            $table->id();

            // Relationship Fields
            $table->foreignId('form_id')->comment('Form that was submitted')->constrained('cms_forms')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->comment('User who submitted the form (if logged in)')->constrained('users')->nullOnDelete();

            // Submission Data
            $table->json('data')->comment('Form submission data as JSON');
            $table->json('metadata')->nullable()->comment('Additional metadata for the submission (custom fields, tracking data, etc.)');

            // Status Fields
            $table->string('status')->default('new')->comment('Submission status: new, read, replied, archived');
            $table->boolean('is_starred')->default(false)->comment('Whether submission is starred/important');
            $table->boolean('is_spam')->default(false)->comment('Whether submission is marked as spam');
            $table->string('ip_address', 45)->nullable()->comment('IP address of submitter');
            $table->timestamp('read_at')->nullable()->comment('When the submission was first read');

            // Audit Fields
            $table->foreignId('deleted_by')->nullable()->comment('User who soft-deleted this submission')->constrained('users')->nullOnDelete();

            // Timestamps
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete timestamp');

            // Indexes for Performance
            $table->index('created_at');
            $table->index('status');
            $table->index('is_starred');
            $table->index('is_spam');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('read_at');
            $table->index(['form_id', 'created_at'], 'form_submissions_form_date_idx');
            $table->index(['form_id', 'status'], 'form_submissions_form_status_idx');
            $table->index(['user_id', 'form_id'], 'form_submissions_user_form_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_form_submissions');
    }
};

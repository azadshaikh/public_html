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
        Schema::create('cms_forms', function (Blueprint $table): void {
            // Primary Key
            $table->id();

            // Basic Form Information
            $table->string('title')->nullable()->comment('Display title of the form');
            $table->string('slug')->nullable()->comment('URL-friendly identifier for the form');
            $table->string('shortcode')->nullable()->comment('Shortcode for embedding the form');

            // Form Template & Type
            $table->string('template')->default('default')->comment('Form template (contact, registration, survey, etc.)');
            $table->string('form_type')->default('standard')->comment('Form type (standard, conversational, multi-step)');

            // Form Content & Styling
            $table->longText('html')->nullable()->comment('HTML markup for the form');
            $table->json('fields')->nullable()->comment('Form fields configuration (JSON structure for drag-drop builder)');
            $table->longText('css')->nullable()->comment('Custom CSS styles for the form');
            $table->longText('js')->nullable()->comment('Custom JavaScript for the form');
            $table->string('feature_image_url')->nullable()->comment('Feature image URL (internal or external)');
            $table->json('metadata')->nullable()->comment('Additional configuration such as autoresponders or integrations');
            $table->json('settings')->nullable()->comment('Form settings (notifications, confirmations, integrations, etc.)');
            $table->json('conditional_logic')->nullable()->comment('Conditional logic rules for fields and notifications');

            // Form Configuration
            $table->boolean('store_in_database')->default(true)->comment('Whether to store form submissions in database');
            $table->longText('email_template')->nullable()->comment('Email template for form notifications');
            $table->json('notification_emails')->nullable()->comment('Multiple email notification configurations');
            $table->boolean('send_autoresponder')->default(false)->comment('Send auto-response to submitter');
            $table->json('autoresponder_config')->nullable()->comment('Auto-responder email configuration');
            $table->longText('confirmations')->nullable()->comment('Confirmation messages for form submissions');

            // Publication & Status
            $table->enum('status', ['draft', 'published'])
                ->default('draft')
                ->index()
                ->comment('Publication status of the form');
            $table->boolean('is_active')->default(true)->comment('Whether form is actively accepting submissions');
            $table->boolean('has_spam_protection')->default(true)->comment('Enable spam protection (honeypot, CAPTCHA)');
            $table->boolean('requires_login')->default(false)->comment('Require user login to submit');
            $table->boolean('limit_one_submission_per_user')->default(false)->comment('Allow only one submission per logged-in user');
            $table->timestamp('published_at')->nullable()->index()->comment('When the form was published');

            // Submission Tracking & Analytics
            $table->unsignedBigInteger('submissions_count')->default(0)->comment('Total number of submissions');
            $table->unsignedBigInteger('unread_count')->default(0)->comment('Number of unread submissions');
            $table->decimal('conversion_rate', 5, 2)->default(0)->comment('Form conversion rate percentage');
            $table->unsignedBigInteger('views_count')->default(0)->comment('Number of times form was viewed');
            $table->timestamp('last_submission_at')->nullable()->comment('Last form submission timestamp');

            // Audit Fields
            $table->foreignId('created_by')->nullable()->comment('User who created this form')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('User who last updated this form')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->comment('User who soft-deleted this form')->constrained('users')->nullOnDelete();

            // Timestamps
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete timestamp');

            // Composite Indexes for Performance
            $table->index(['status', 'published_at'], 'form_status_published_idx');
            $table->index(['status', 'is_active'], 'forms_status_active_idx');
            $table->index('is_active');
            $table->index('template');
            $table->index('form_type');
            $table->index('last_submission_at');
            $table->fullText(['title'], 'form_search_idx');
            $table->unique(['slug', 'deleted_at'], 'cms_forms_slug_unique');
            $table->unique(['shortcode', 'deleted_at'], 'cms_forms_shortcode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_forms');
    }
};

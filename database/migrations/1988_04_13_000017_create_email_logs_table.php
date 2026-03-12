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
        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name')->nullable();
            $table->foreignId('email_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name')->nullable();
            $table->foreignId('sent_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->string('status', 50)->index();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->json('recipients')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable()->index();

            // Audit fields for AuditableTrait
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index for performance
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

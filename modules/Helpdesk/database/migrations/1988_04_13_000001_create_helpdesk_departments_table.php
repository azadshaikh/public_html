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
        Schema::create('helpdesk_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->mediumText('description')->nullable();
            $table->foreignId('department_head')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('metadata')->nullable()->comment('Additional department settings like escalation rules');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_departments');
    }
};

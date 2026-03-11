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
        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('display_name')->nullable();
            $table->string('group')->nullable();
            $table->string('module_slug')->nullable();
            $table->text('description')->nullable();
            $table->index('group');
            $table->index('module_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropIndex(['group']);
            $table->dropIndex(['module_slug']);
            $table->dropColumn(['display_name', 'group', 'module_slug', 'description']);
        });
    }
};

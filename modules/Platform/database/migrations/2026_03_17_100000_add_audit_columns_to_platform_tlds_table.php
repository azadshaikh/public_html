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
        if (! Schema::hasTable('platform_tlds')) {
            return;
        }

        if (! Schema::hasColumn('platform_tlds', 'created_by')) {
            Schema::table('platform_tlds', function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('platform_tlds', 'updated_by')) {
            Schema::table('platform_tlds', function (Blueprint $table): void {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('platform_tlds', 'deleted_by')) {
            Schema::table('platform_tlds', function (Blueprint $table): void {
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('platform_tlds')) {
            return;
        }

        Schema::table('platform_tlds', function (Blueprint $table): void {
            foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
                if (Schema::hasColumn('platform_tlds', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};

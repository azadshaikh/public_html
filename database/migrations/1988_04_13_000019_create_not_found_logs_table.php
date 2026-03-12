<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('not_found_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('url', 2048);
            $table->string('full_url', 4096)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->onDelete('set null');
            $table->string('method', 10)->default('GET');
            $table->boolean('is_bot')->default(false)->index();
            $table->boolean('is_suspicious')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); // Future-proofing
            $table->softDeletes();

            // Composite indexes with prefix for long columns (MySQL max key = 3072 bytes)
            // Use prefix of 191 chars (191 * 4 = 764 bytes in utf8mb4)
            $table->index(['is_suspicious', 'created_at']);
            $table->index(['is_bot', 'created_at']);
        });

        $driver = DB::getDriverName();

        // Add prefix indexes for long VARCHAR columns using raw SQL (MySQL/MariaDB only)
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `not_found_logs` ADD INDEX `not_found_logs_url_index` (`url`(191))');
            DB::statement('ALTER TABLE `not_found_logs` ADD INDEX `not_found_logs_referer_index` (`referer`(191))');
            DB::statement('ALTER TABLE `not_found_logs` ADD INDEX `not_found_logs_url_created_at_index` (`url`(191), `created_at`)');
            DB::statement('ALTER TABLE `not_found_logs` ADD INDEX `not_found_logs_referer_created_at_index` (`referer`(191), `created_at`)');
            DB::statement('ALTER TABLE `not_found_logs` ADD INDEX `not_found_logs_ip_created_at_index` (`ip_address`, `created_at`)');

            return;
        }

        // PostgreSQL (and others): create comparable prefix-style indexes to keep index rows bounded.
        // (Mirrors the MySQL 191-char prefix behavior without relying on MySQL-only syntax.)
        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX not_found_logs_url_index ON not_found_logs ((left(url, 191)))');
            DB::statement('CREATE INDEX not_found_logs_referer_index ON not_found_logs ((left(referer, 191)))');
            DB::statement('CREATE INDEX not_found_logs_url_created_at_index ON not_found_logs ((left(url, 191)), created_at)');
            DB::statement('CREATE INDEX not_found_logs_referer_created_at_index ON not_found_logs ((left(referer, 191)), created_at)');
            DB::statement('CREATE INDEX not_found_logs_ip_created_at_index ON not_found_logs (ip_address, created_at)');

            return;
        }

        Schema::table('not_found_logs', function (Blueprint $table): void {
            $table->index('url', 'not_found_logs_url_index');
            $table->index('referer', 'not_found_logs_referer_index');
            $table->index(['url', 'created_at'], 'not_found_logs_url_created_at_index');
            $table->index(['referer', 'created_at'], 'not_found_logs_referer_created_at_index');
            $table->index(['ip_address', 'created_at'], 'not_found_logs_ip_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
    }
};

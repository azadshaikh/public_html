<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the plan prices table
        Schema::create('subscriptions_plan_prices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('plan_id')->constrained('subscriptions_plans')->cascadeOnDelete();

            $table->string('billing_cycle')->default('monthly'); // monthly, quarterly, yearly, lifetime
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['plan_id', 'is_active']);
            $table->index('billing_cycle');
        });

        // 2. Migrate existing price data from plans → plan_prices
        DB::table('subscriptions_plans')
            ->whereNull('deleted_at')
            ->get(['id', 'billing_cycle', 'price', 'currency'])
            ->each(function (object $plan): void {
                DB::table('subscriptions_plan_prices')->insert([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $plan->billing_cycle,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // 3. Add plan_price_id + billing_cycle snapshot to subscriptions (if table exists)
        if (Schema::hasTable('subscriptions_subscriptions')) {
            Schema::table('subscriptions_subscriptions', function (Blueprint $table): void {
                $table->foreignId('plan_price_id')
                    ->nullable()
                    ->after('plan_id')
                    ->constrained('subscriptions_plan_prices')
                    ->nullOnDelete();

                // Billing cycle snapshot (denormalised for query performance)
                $table->string('billing_cycle')->nullable()->after('plan_price_id');
            });

            // 4. Backfill billing_cycle on existing subscriptions from their plan
            // Use raw SQL for PostgreSQL-compatible UPDATE FROM syntax
            DB::statement('
                UPDATE subscriptions_subscriptions s
                SET billing_cycle = p.billing_cycle
                FROM subscriptions_plans p
                WHERE s.plan_id = p.id
                AND s.billing_cycle IS NULL
            ');
        }

        // 5. Remove the now-redundant price columns from plans
        Schema::table('subscriptions_plans', function (Blueprint $table): void {
            $table->dropIndex(['billing_cycle']);
            $table->dropColumn(['billing_cycle', 'price', 'currency']);
        });
    }

    public function down(): void
    {
        // Restore columns on plans
        Schema::table('subscriptions_plans', function (Blueprint $table): void {
            $table->string('billing_cycle')->default('monthly')->after('description');
            $table->decimal('price', 10, 2)->default(0)->after('billing_cycle');
            $table->string('currency', 3)->default('USD')->after('price');
            $table->index('billing_cycle');
        });

        // Remove added columns from subscriptions
        if (Schema::hasTable('subscriptions_subscriptions')) {
            Schema::table('subscriptions_subscriptions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('plan_price_id');
                $table->dropColumn('billing_cycle');
            });
        }

        // Drop plan prices table
        Schema::dropIfExists('subscriptions_plan_prices');
    }
};

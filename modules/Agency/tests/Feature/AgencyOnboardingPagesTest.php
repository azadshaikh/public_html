<?php

declare(strict_types=1);

namespace Modules\Agency\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Agency\Models\AgencyWebsite;
use Modules\Agency\Providers\AgencyServiceProvider;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPrice;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class AgencyOnboardingPagesTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Plan $plan;

    private PlanPrice $planPrice;

    private AgencyWebsite $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('agency-onboarding-pages.json', [
            'Agency' => 'enabled',
            'Customers' => 'enabled',
            'Subscriptions' => 'enabled',
        ]);

        $this->ensureAgencyModuleBooted();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create([
            'first_name' => 'Agency',
            'last_name' => 'Owner',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->user->assignRole(Role::findByName('super_user', 'web'));

        $this->plan = Plan::factory()->create([
            'name' => 'Starter',
            'trial_days' => 7,
            'is_popular' => true,
            'is_active' => true,
        ]);

        $this->planPrice = $this->plan->prices()->firstOrFail();

        $this->website = AgencyWebsite::query()->create([
            'site_id' => 'site_456',
            'domain' => 'launch.astero.site',
            'name' => 'Launch Site',
            'type' => 'trial',
            'status' => WebsiteStatus::WaitingForDns->value,
            'owner_id' => $this->user->id,
            'owner_email' => $this->user->email,
            'owner_name' => trim($this->user->first_name.' '.$this->user->last_name),
            'is_www' => true,
            'plan' => 'Starter',
        ]);

        $this->customer = Customer::factory()->individual()->create([
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'contact_first_name' => $this->user->first_name,
            'contact_last_name' => $this->user->last_name,
            'status' => Status::ACTIVE,
            'metadata' => [
                'onboarding' => [
                    'domain' => 'launch.astero.site',
                    'domain_type' => 'subdomain',
                    'dns_mode' => 'subdomain',
                    'selected_plan_id' => $this->plan->id,
                    'selected_plan_price_id' => $this->planPrice->id,
                    'current_step' => 'provisioning',
                    'website' => [
                        'name' => 'Launch Site',
                        'domain' => 'launch.astero.site',
                    ],
                    'last_site_id' => $this->website->id,
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_domain_step_renders_as_an_inertia_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('agency.onboarding.domain'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/onboarding/domain')
                ->where('savedDomain', 'launch.astero.site')
                ->where('savedDomainType', 'subdomain'));
    }

    public function test_plan_step_renders_as_an_inertia_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('agency.onboarding.plans'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/onboarding/plans')
                ->where('plans.0.name', 'Starter')
                ->where('selectedPlanId', $this->plan->id));
    }

    public function test_checkout_step_renders_as_an_inertia_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('agency.onboarding.checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/onboarding/checkout')
                ->where('selectedPlan.name', 'Starter')
                ->where('websiteDetails.domain', 'launch.astero.site'));
    }

    public function test_provisioning_step_renders_as_an_inertia_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('agency.onboarding.provisioning.website', $this->website->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/onboarding/provisioning')
                ->where('website.domain', 'launch.astero.site')
                ->has('statusData.progress')
                ->has('statusData.next_actions'));
    }

    private function ensureAgencyModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('agency.onboarding.domain')) {
            app()->register(AgencyServiceProvider::class);
        }

        if (! Route::has('agency.onboarding.domain')) {
            Route::middleware('web')->group(base_path('modules/Agency/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        $this->runModuleMigrationsIfMissing(
            'customers_customers',
            base_path('modules/Customers/database/migrations'),
        );
        $this->runModuleMigrationsIfMissing(
            'subscriptions_plans',
            base_path('modules/Subscriptions/database/migrations'),
        );
        $this->runModuleMigrationsIfMissing(
            'agency_websites',
            base_path('modules/Agency/database/migrations'),
        );
    }

    private function runModuleMigrationsIfMissing(string $table, string $path): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);
    }
}

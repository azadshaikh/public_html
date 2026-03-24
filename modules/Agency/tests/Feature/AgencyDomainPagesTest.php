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
use Modules\Agency\Models\AgencyWebsite;
use Modules\Agency\Providers\AgencyServiceProvider;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class AgencyDomainPagesTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('agency-domain-pages.json', [
            'Agency' => 'enabled',
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

        AgencyWebsite::query()->create([
            'site_id' => 'site_123',
            'domain' => 'example.test',
            'name' => 'Example Site',
            'type' => 'paid',
            'status' => 'active',
            'owner_id' => $this->user->id,
            'owner_email' => $this->user->email,
            'owner_name' => trim($this->user->first_name.' '.$this->user->last_name),
            'is_www' => true,
            'plan' => 'Starter',
            'metadata' => ['dns_mode' => 'managed'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_domains_index_renders_inertia_with_inactive_default_filters(): void
    {
        $this->actingAs($this->user)
            ->get(route('agency.domains.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/domains/index')
                ->where('rows.data.0.domain', 'example.test')
                ->where('filters.status', '')
                ->where('filters.dns_mode', '')
                ->where('config.filters.0.key', 'dns_mode')
                ->where('config.filters.0.options.0.value', '')
                ->where('config.filters.1.key', 'status')
                ->where('config.filters.1.options.0.value', ''));
    }

    private function ensureAgencyModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('agency.domains.index')) {
            app()->register(AgencyServiceProvider::class);
        }

        if (! Route::has('agency.domains.index')) {
            Route::middleware('web')->group(base_path('modules/Agency/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        if (! Schema::hasTable('agency_websites')) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/Agency/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }
}

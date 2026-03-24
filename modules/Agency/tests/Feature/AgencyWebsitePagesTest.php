<?php

declare(strict_types=1);

namespace Modules\Agency\Tests\Feature;

use App\Enums\Status;
use App\Http\Middleware\HandleInertiaRequests;
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

class AgencyWebsitePagesTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('agency-website-pages.json', [
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
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_websites_index_renders_as_an_inertia_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('agency.websites.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/websites/index')
                ->where('rows.data.0.name', 'Example Site')
                ->where('config.settings.entityPlural', 'websites')
                ->where('filters.status', 'all'));
    }

    public function test_inertia_xhr_request_to_websites_index_does_not_fall_back_to_plain_json(): void
    {
        $this->withoutMiddleware([HandleInertiaRequests::class]);

        $this->actingAs($this->user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get(route('agency.websites.index'))
            ->assertOk()
            ->assertJsonPath('component', 'agency/websites/index')
            ->assertJsonPath('props.rows.data.0.name', 'Example Site')
            ->assertJsonMissingPath('status');
    }

    public function test_explicit_data_endpoint_still_returns_plain_json(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('agency.websites.data'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.items.0.name', 'Example Site');
    }

    private function ensureAgencyModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('agency.websites.index')) {
            app()->register(AgencyServiceProvider::class);
        }

        if (! Route::has('agency.websites.index')) {
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

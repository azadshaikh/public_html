<?php

declare(strict_types=1);

namespace Modules\Agency\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Agency\Providers\AgencyServiceProvider;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class AgencyAuthPagesTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('agency-auth-pages.json', [
            'Agency' => 'enabled',
        ]);

        $this->ensureAgencyModuleBooted();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_agency_sign_in_page_renders_as_an_inertia_page(): void
    {
        $this->get(route('agency.sign-in'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/auth/sign-in')
                ->has('canResetPassword')
                ->has('canRegister')
                ->has('socialProviders.google')
                ->has('socialProviders.github'));
    }

    public function test_agency_register_page_renders_as_an_inertia_page(): void
    {
        $this->get(route('agency.get-started'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('agency/auth/register')
                ->has('canLogin')
                ->has('socialProviders.google')
                ->has('socialProviders.github'));
    }

    public function test_default_login_route_redirects_to_the_agency_sign_in_page(): void
    {
        $this->get(route('login'))
            ->assertRedirect(route('agency.sign-in'));
    }

    public function test_default_register_route_redirects_to_the_agency_register_page(): void
    {
        $this->get(route('register'))
            ->assertRedirect(route('agency.get-started'));
    }

    private function ensureAgencyModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('agency.sign-in')) {
            app()->register(AgencyServiceProvider::class);
        }

        if (! Route::has('agency.sign-in')) {
            Route::middleware('web')->group(base_path('modules/Agency/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }
    }
}

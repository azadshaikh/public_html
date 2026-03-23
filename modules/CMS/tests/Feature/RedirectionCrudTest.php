<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Models\Redirection;
use Tests\TestCase;

class RedirectionCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_redirections', 'add_redirections', 'edit_redirections', 'delete_redirections', 'restore_redirections'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'redirections',
                    'module_slug' => 'cms',
                ],
            );
        }

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
        $this->admin->givePermissionTo([
            'view_redirections',
            'add_redirections',
            'edit_redirections',
            'delete_redirections',
            'restore_redirections',
        ]);
    }

    public function test_guests_are_redirected_from_redirections_create_page(): void
    {
        $this->get(route('cms.redirections.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_redirections_index(): void
    {
        $this->createRedirection('/legacy-page', '/new-page');

        $this->actingAs($this->admin)
            ->get(route('cms.redirections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/redirections/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.source_url', '/legacy-page')
                ->where('rows.data.0.target_url', '/new-page')
            );
    }

    public function test_redirections_index_exposes_filter_options_and_round_trips_filter_state(): void
    {
        $matchingRedirection = $this->createRedirection('/legacy-page', '/new-page');
        $this->createRedirection('/temporary-page', '/external-target', 302, 'external', 'wildcard', 'inactive');

        $this->actingAs($this->admin)
            ->get(route('cms.redirections.index', [
                'redirect_type' => '301',
                'url_type' => 'internal',
                'match_type' => 'exact',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/redirections/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.source_url', $matchingRedirection->source_url)
                ->where('config.filters.0.options.301', '301 Moved Permanently')
                ->where('config.filters.1.options.internal', 'Internal')
                ->where('config.filters.2.options.exact', 'Exact Match')
                ->where('filters.redirect_type', '301')
                ->where('filters.url_type', 'internal')
                ->where('filters.match_type', 'exact')
            );
    }

    public function test_admin_can_access_redirections_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.redirections.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/redirections/create')
                ->where('initialValues.redirect_type', '301')
                ->where('initialValues.url_type', 'internal')
                ->where('initialValues.match_type', 'exact')
                ->where('initialValues.status', 'active')
                ->has('statusOptions')
                ->has('redirectTypeOptions')
                ->has('urlTypeOptions')
                ->has('matchTypeOptions')
                ->where('baseUrl', rtrim(url('/'), '/'))
            );
    }

    public function test_admin_can_access_redirections_edit_page(): void
    {
        $redirection = $this->createRedirection(
            '/legacy-page',
            '/new-page',
            expiresAt: Carbon::now()->addWeek(),
        );

        $this->actingAs($this->admin)
            ->get(route('cms.redirections.edit', $redirection))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/redirections/edit')
                ->where('redirection.id', $redirection->id)
                ->where('redirection.source_url', '/legacy-page')
                ->where('initialValues.source_url', '/legacy-page')
                ->where('initialValues.target_url', '/new-page')
                ->where('initialValues.redirect_type', '301')
                ->where('initialValues.expires_at', $redirection->expires_at?->format('Y-m-d\TH:i'))
            );
    }

    public function test_public_request_is_redirected_when_matching_active_redirection(): void
    {
        $this->createRedirection(
            '/go-to-google',
            'https://www.google.com',
            301,
            'external',
        );

        $this->get('/go-to-google')
            ->assertRedirect('https://www.google.com');
    }

    public function test_admin_can_access_redirections_import_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.redirections.import.form'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/redirections/import')
            );
    }

    public function test_admin_can_store_a_redirection(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.redirections.store'), [
                'source_url' => '/old-page',
                'target_url' => '/new-page',
                'redirect_type' => '301',
                'url_type' => 'internal',
                'match_type' => 'exact',
                'status' => 'active',
                'notes' => 'Migrated from the old marketing site.',
                'expires_at' => '',
            ]);

        $redirection = Redirection::query()->where('source_url', '/old-page')->firstOrFail();

        $response->assertRedirect(route('cms.redirections.edit', $redirection));

        $this->assertSame('/new-page', $redirection->target_url);
        $this->assertSame(301, $redirection->redirect_type);
        $this->assertSame('internal', $redirection->url_type);
        $this->assertSame('exact', $redirection->match_type);
    }

    public function test_admin_can_update_a_redirection(): void
    {
        $redirection = $this->createRedirection('/outdated-page', '/replacement-page');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.redirections.update', $redirection), [
                'source_url' => '/outdated-page',
                'target_url' => 'https://example.com/updated-page',
                'redirect_type' => '302',
                'url_type' => 'external',
                'match_type' => 'exact',
                'status' => 'inactive',
                'notes' => 'Temporarily routed to an external landing page.',
                'expires_at' => Carbon::now()->addDays(3)->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('cms.redirections.edit', $redirection));

        $redirection->refresh();

        $this->assertSame('https://example.com/updated-page', $redirection->target_url);
        $this->assertSame(302, $redirection->redirect_type);
        $this->assertSame('external', $redirection->url_type);
        $this->assertSame('inactive', $redirection->status);
    }

    private function createRedirection(
        string $sourceUrl,
        string $targetUrl,
        int $redirectType = 301,
        string $urlType = 'internal',
        string $matchType = 'exact',
        string $status = 'active',
        ?Carbon $expiresAt = null,
    ): Redirection {
        return Redirection::query()->create([
            'source_url' => $sourceUrl,
            'target_url' => $targetUrl,
            'redirect_type' => $redirectType,
            'url_type' => $urlType,
            'match_type' => $matchType,
            'status' => $status,
            'hits' => 0,
            'notes' => 'Seeded redirection for a feature test.',
            'expires_at' => $expiresAt,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }
}

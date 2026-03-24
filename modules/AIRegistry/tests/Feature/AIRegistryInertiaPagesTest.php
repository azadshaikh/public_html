<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Tests\Feature;

use App\Enums\Status;
use App\Http\Middleware\EnsureModuleIsEnabled;
use App\Models\Permission;
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
use Modules\AIRegistry\Models\AiModel;
use Modules\AIRegistry\Models\AiProvider;
use Modules\AIRegistry\Providers\AIRegistryServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AIRegistryInertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureModuleIsEnabled::class);

        $this->ensureAiRegistryModuleBooted();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            'view_ai_providers',
            'add_ai_providers',
            'edit_ai_providers',
            'delete_ai_providers',
            'restore_ai_providers',
            'view_ai_models',
            'add_ai_models',
            'edit_ai_models',
            'delete_ai_models',
            'restore_ai_models',
        ] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => str_contains($permission, 'providers') ? 'ai_providers' : 'ai_models',
                    'module_slug' => 'airegistry',
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create([
            'first_name' => 'AI',
            'last_name' => 'Admin',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->admin->assignRole(Role::findByName('super_user', 'web'));
        $this->admin->givePermissionTo([
            'view_ai_providers',
            'add_ai_providers',
            'edit_ai_providers',
            'delete_ai_providers',
            'restore_ai_providers',
            'view_ai_models',
            'add_ai_models',
            'edit_ai_models',
            'delete_ai_models',
            'restore_ai_models',
        ]);

        $this->admin = $this->admin->fresh();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->ensureAiRegistryModuleBooted();
    }

    public function test_ai_registry_create_pages_render_with_inertia(): void
    {
        $provider = AiProvider::query()->create([
            'slug' => 'openai',
            'name' => 'OpenAI',
            'capabilities' => ['text', 'streaming'],
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->followingRedirects()
            ->get(route('ai-registry.providers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ai-registry/providers/create')
                ->has('initialValues')
                ->where('initialValues.is_active', true)
                ->has('capabilityOptions'));

        $this->actingAs($this->admin)
            ->followingRedirects()
            ->get(route('ai-registry.models.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ai-registry/models/create')
                ->has('initialValues')
                ->where('initialValues.provider_id', '')
                ->has('providerOptions', 1)
                ->where('providerOptions.0.value', (string) $provider->id)
                ->where('providerOptions.0.label', 'OpenAI')
                ->has('capabilityOptions')
                ->has('categoryOptions')
                ->has('inputModalityOptions')
                ->has('outputModalityOptions'));
    }

    public function test_ai_registry_index_pages_render_refactored_inertia_contracts(): void
    {
        $provider = AiProvider::query()->create([
            'slug' => 'openrouter',
            'name' => 'OpenRouter',
            'docs_url' => 'https://openrouter.ai/docs',
            'api_key_url' => 'https://openrouter.ai/keys',
            'capabilities' => ['text', 'reasoning'],
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $model = AiModel::query()->create([
            'provider_id' => $provider->id,
            'slug' => 'gpt-4.1-mini',
            'name' => 'GPT-4.1 Mini',
            'description' => 'Compact reasoning model',
            'context_window' => 128000,
            'max_output_tokens' => 8192,
            'input_cost_per_1m' => 0.15,
            'output_cost_per_1m' => 0.6,
            'input_modalities' => ['text'],
            'output_modalities' => ['text'],
            'tokenizer' => 'cl100k_base',
            'is_moderated' => true,
            'supported_parameters' => ['temperature', 'top_p'],
            'capabilities' => ['text', 'reasoning'],
            'categories' => ['programming'],
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->followingRedirects()
            ->get(route('ai-registry.providers.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ai-registry/providers/index')
                ->has('config.columns')
                ->has('config.actions')
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'OpenRouter')
                ->where('rows.data.0.models_count', 1)
                ->where('rows.data.0.capabilities.0.label', 'Text')
                ->where('statistics.total', 1));

        $this->actingAs($this->admin)
            ->followingRedirects()
            ->get(route('ai-registry.models.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ai-registry/models/index')
                ->has('config.columns')
                ->has('config.actions')
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'GPT-4.1 Mini')
                ->where('rows.data.0.provider_name', 'OpenRouter')
                ->where('rows.data.0.context_window_formatted', '128K')
                ->where('statistics.total', 1)
                ->where('statistics.active', 1));

        $this->assertSame('gpt-4.1-mini', $model->slug);
    }

    private function ensureAiRegistryModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('ai-registry.providers.create')) {
            app()->register(AIRegistryServiceProvider::class);
        }

        if (! Route::has('ai-registry.providers.create')) {
            Route::middleware(['web', 'module.enabled:airegistry'])
                ->group(base_path('modules/AIRegistry/routes/web.php'));

            Route::middleware('api')
                ->prefix('api/ai-registry')
                ->group(base_path('modules/AIRegistry/routes/api.php'));

            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        if (! $this->aiRegistryTablesExist()) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/AIRegistry/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    private function aiRegistryTablesExist(): bool
    {
        return Schema::hasTable('airegistry_providers')
            && Schema::hasTable('airegistry_models');
    }
}

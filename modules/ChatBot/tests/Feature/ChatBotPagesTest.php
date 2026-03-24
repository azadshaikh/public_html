<?php

declare(strict_types=1);

namespace Modules\ChatBot\Tests\Feature;

use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\ChatBot\Database\Seeders\ChatBotPermissionSeeder;
use Modules\ChatBot\Providers\ChatBotServiceProvider;
use Tests\TestCase;

class ChatBotPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureChatBotModuleBooted();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChatBotPermissionSeeder::class);

        app('cache')->driver('array')->flush();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_user');
    }

    public function test_chat_workspace_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.chatbot.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('chatbot/index')
                    ->where('auth.abilities.useChatbot', true),
            );
    }

    public function test_chat_new_conversation_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.chatbot.new'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page->component('chatbot/index'),
            );
    }

    public function test_chat_settings_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.chatbot.settings.index', ['section' => 'general']))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('chatbot/settings/index')
                    ->has('section')
                    ->has('initialValues')
                    ->has('providerOptions')
                    ->has('toolGroups')
                    ->where('auth.abilities.manageChatbotSettings', true),
            );
    }

    public function test_chat_settings_redirects_without_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.chatbot.settings.index'))
            ->assertRedirect();
    }

    public function test_chat_workspace_requires_authentication(): void
    {
        $this->get(route('app.chatbot.index'))
            ->assertRedirect(route('login'));
    }

    public function test_chat_workspace_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs($user)
            ->get(route('app.chatbot.index'))
            ->assertForbidden();
    }

    private function ensureChatBotModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('app.chatbot.index')) {
            app()->register(ChatBotServiceProvider::class);
        }

        if (! Route::has('app.chatbot.index')) {
            Route::middleware('web')->group(base_path('modules/ChatBot/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        if (! $this->chatBotTablesExist()) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/ChatBot/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    private function chatBotTablesExist(): bool
    {
        return Schema::hasTable('agent_conversations')
            && Schema::hasTable('agent_conversation_messages');
    }
}

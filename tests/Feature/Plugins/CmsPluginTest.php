<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsPluginTest extends TestCase
{
    public function test_guests_are_redirected_away_from_the_cms_plugin(): void
    {
        $this->get(route('cms.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_sample_cms_plugin_dashboard(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/index')
                ->where('module.name', 'CMS')
                ->where('module.slug', 'cms')
                ->where('module.version', '1.0.0'));
    }
}

<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Models\CmsPost;
use Tests\TestCase;

class AccessProtectionPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['theme.active' => 'default']);
    }

    public function test_site_access_protection_form_renders_with_inertia(): void
    {
        $this->get(route('site.access.protection.form'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/site-access-protection/form')
                ->has('message'));
    }

    public function test_post_access_protection_form_renders_with_inertia(): void
    {
        $post = CmsPost::query()->create([
            'title' => 'Protected Post',
            'slug' => 'protected-post',
            'type' => 'post',
            'status' => 'published',
            'visibility' => 'password',
            'post_password' => Hash::make('secret'),
            'password_hint' => 'Use the shared password.',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('post.access.protection.form', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/post-access-protection/form')
                ->where('post.id', $post->id)
                ->where('post.title', 'Protected Post')
                ->where('post.password_hint', 'Use the shared password.'));
    }
}

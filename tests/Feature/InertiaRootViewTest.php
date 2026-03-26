<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaRootViewTest extends TestCase
{
    public function test_sign_in_page_renders_the_inertia_v3_root_shell(): void
    {
        $response = $this->followingRedirects()->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('<title data-inertia>', false)
            ->assertDontSee('<title inertia>', false)
            ->assertSee('<script data-page="app" type="application/json">', false)
            ->assertSee('<div id="app"></div>', false);
    }
}

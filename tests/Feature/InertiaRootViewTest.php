<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaRootViewTest extends TestCase
{
    public function test_login_page_renders_inertia_root_title_fallback(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('<title data-inertia>', false);
    }
}

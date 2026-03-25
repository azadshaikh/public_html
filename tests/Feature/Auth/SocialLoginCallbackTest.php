<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SocialLoginCallbackTest extends TestCase
{
    public function test_google_callback_is_rejected_when_social_auth_is_disabled(): void
    {
        config([
            'services.social_auth.enabled' => false,
            'services.google.enabled' => true,
        ]);

        $response = $this->get(route('social.login.callback', 'google'));

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('error', __('auth.social_auth_disabled'));
    }
}

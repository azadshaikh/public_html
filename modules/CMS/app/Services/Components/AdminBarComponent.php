<?php

namespace Modules\CMS\Services\Components;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\CMS\View\Components\AdminBar;

/**
 * Admin Bar Component
 * Renders the admin toolbar for logged-in users
 * Usage: {admin_bar}
 */
class AdminBarComponent extends ThemeComponent
{
    public function render(array $params, $template = null): string
    {
        // Check if user is authenticated and has admin access
        if (! auth()->check()) {
            return '';
        }

        try {
            $component = resolve(AdminBar::class);

            return $component->render()->render();
        } catch (Exception $exception) {
            Log::error('Admin bar component failed', [
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }
}

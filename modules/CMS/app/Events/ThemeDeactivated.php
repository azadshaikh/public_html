<?php

namespace Modules\CMS\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThemeDeactivated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public array $themeInfo, public ?array $newTheme = null) {}

    /**
     * Get the theme directory that was deactivated
     */
    public function getThemeDirectory(): string
    {
        return $this->themeInfo['directory'];
    }

    /**
     * Get the theme name that was deactivated
     */
    public function getThemeName(): string
    {
        return $this->themeInfo['name'];
    }

    /**
     * Get the new theme directory (if any)
     */
    public function getNewThemeDirectory(): ?string
    {
        return $this->newTheme['directory'] ?? null;
    }

    /**
     * Check if this is switching to another theme
     */
    public function isThemeSwitch(): bool
    {
        return $this->newTheme !== null;
    }
}

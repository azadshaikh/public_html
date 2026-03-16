<?php

namespace Modules\CMS\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThemeActivated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public array $themeInfo, public ?array $previousTheme = null) {}

    /**
     * Get the theme directory that was activated
     */
    public function getThemeDirectory(): string
    {
        return $this->themeInfo['directory'];
    }

    /**
     * Get the theme name that was activated
     */
    public function getThemeName(): string
    {
        return $this->themeInfo['name'];
    }

    /**
     * Get the previous theme directory (if any)
     */
    public function getPreviousThemeDirectory(): ?string
    {
        return $this->previousTheme['directory'] ?? null;
    }

    /**
     * Check if this is switching from another theme
     */
    public function isThemeSwitch(): bool
    {
        return $this->previousTheme !== null;
    }
}

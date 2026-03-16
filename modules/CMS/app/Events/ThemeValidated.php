<?php

namespace Modules\CMS\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThemeValidated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public array $themeInfo, public bool $isValid, public array $validationErrors = []) {}

    /**
     * Get the theme directory that was validated
     */
    public function getThemeDirectory(): string
    {
        return $this->themeInfo['directory'];
    }

    /**
     * Get the theme name that was validated
     */
    public function getThemeName(): string
    {
        return $this->themeInfo['name'];
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Get validation errors
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Check if there are validation errors
     */
    public function hasErrors(): bool
    {
        return $this->validationErrors !== [];
    }
}

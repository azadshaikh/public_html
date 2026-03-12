<?php

declare(strict_types=1);

namespace App\Scaffold;

/**
 * StatusTab - Fluent status tab builder for scaffold definitions
 *
 * @example
 * StatusTab::make('all')->label('All')->default()
 * StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')
 * StatusTab::make('pending')->label('Pending')->color('warning')
 */
class StatusTab
{
    public string $key;

    public string $label;

    public ?string $icon = null;

    public ?string $color = null;

    public mixed $value = null;

    public bool $isDefault = false;

    public ?int $count = null;

    public ?string $url = null;

    public array $meta = [];

    /**
     * Create a status tab
     */
    public static function make(string $key): self
    {
        $tab = new self;
        $tab->key = $key;
        $tab->label = str($key)->headline()->toString();
        $tab->value = $key === 'all' ? null : $key;

        return $tab;
    }

    /**
     * Create tabs from an enum
     *
     * @return array<StatusTab>
     */
    public static function fromEnum(string $enumClass, bool $includeAll = true): array
    {
        $tabs = [];

        if ($includeAll) {
            $tabs[] = self::make('all')->label('All')->default();
        }

        foreach ($enumClass::cases() as $case) {
            $tab = self::make($case->value)
                ->label(method_exists($case, 'label') ? $case->label() : $case->name);

            // Check for color method
            if (method_exists($case, 'color')) {
                $tab->color($case->color());
            }

            // Check for icon method
            if (method_exists($case, 'icon')) {
                $tab->icon($case->icon());
            }

            $tabs[] = $tab;
        }

        return $tabs;
    }

    /**
     * Set tab label
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set tab icon
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set tab color
     */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Set filter value
     */
    public function value(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Mark as default tab
     */
    public function default(bool $default = true): self
    {
        $this->isDefault = $default;

        return $this;
    }

    /**
     * Set count (usually set dynamically)
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Set URL for this status tab
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Add metadata
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Convert to array for JSON
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'color' => $this->color,
            'value' => $this->value,
            'isDefault' => $this->isDefault ?: null,
            'count' => $this->count,
            'url' => $this->url,
            ...$this->meta,
        ], fn ($v): bool => $v !== null);
    }
}

<?php

declare(strict_types=1);

namespace App\Scaffold;

use BackedEnum;
use UnitEnum;

/**
 * Filter - Fluent filter builder for scaffold definitions
 *
 * @example
 * Filter::select('status')->label('Status')->options(Status::class)
 * Filter::dateRange('created_at')->label('Created Date')
 * Filter::search('name')->label('Name')->placeholder('Search by name...')
 */
class Filter
{
    public string $key;

    public string $label;

    public string $type = 'select';

    public ?array $options = null;

    public ?string $placeholder = null;

    public mixed $default = null;

    public bool $multiple = false;

    public ?string $dependsOn = null;

    public array $meta = [];

    public bool $useDatepicker = false;

    /**
     * Create a select filter
     */
    public static function select(string $key): self
    {
        $filter = new self;
        $filter->key = $key;
        $filter->label = str($key)->headline()->toString();
        $filter->type = 'select';

        return $filter;
    }

    /**
     * Create a search/text filter
     */
    public static function search(string $key): self
    {
        $filter = new self;
        $filter->key = $key;
        $filter->label = str($key)->headline()->toString();
        $filter->type = 'search';

        return $filter;
    }

    /**
     * Create a date range filter
     */
    public static function dateRange(string $key): self
    {
        $filter = new self;
        $filter->key = $key;
        $filter->label = str($key)->headline()->toString();
        $filter->type = 'date_range';
        $filter->useDatepicker = true;

        return $filter;
    }

    /**
     * Create a date filter
     */
    public static function date(string $key): self
    {
        $filter = new self;
        $filter->key = $key;
        $filter->label = str($key)->headline()->toString();
        $filter->type = 'date';

        return $filter;
    }

    /**
     * Create a boolean filter
     */
    public static function boolean(string $key): self
    {
        $filter = new self;
        $filter->key = $key;
        $filter->label = str($key)->headline()->toString();
        $filter->type = 'boolean';
        $filter->options = [
            '1' => 'Yes',
            '0' => 'No',
        ];

        return $filter;
    }

    /**
     * Create a number filter (custom type)
     */
    public static function number(string $key): self
    {
        $filter = new self;
        $filter->key = $key;
        $filter->label = str($key)->headline()->toString();
        $filter->type = 'number';

        return $filter;
    }

    /**
     * Set filter label
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set filter options
     *
     * @param  array|string  $options  Array or Enum class
     */
    public function options(array|string $options): self
    {
        if (is_string($options) && enum_exists($options)) {
            $this->options = collect($options::cases())
                ->mapWithKeys(function (UnitEnum $case): array {
                    $key = $case instanceof BackedEnum ? (string) $case->value : $case->name;
                    $label = method_exists($case, 'label') ? (string) $case->label() : $case->name;

                    return [$key => $label];
                })
                ->all();
        } else {
            $this->options = $options;
        }

        return $this;
    }

    /**
     * Set placeholder text
     */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Set default value
     */
    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Allow multiple selection
     */
    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Set dependency on another filter
     */
    public function dependsOn(string $filterKey): self
    {
        $this->dependsOn = $filterKey;

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
     * Enable/disable datepicker UI for date range filters.
     */
    public function useDatepicker(bool $enabled = true): self
    {
        $this->useDatepicker = $enabled;

        return $this;
    }

    /**
     * Convert to array for Inertia props.
     */
    public function toArray(): array
    {
        $options = $this->options;

        // Normalize options to list format if they are associative
        if (is_array($options) && $options !== [] && ! array_is_list($options)) {
            $options = collect($options)
                ->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => $label])
                ->values()
                ->all();
        }

        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $options,
            'placeholder' => $this->placeholder,
            'default' => $this->default,
            'multiple' => $this->multiple ?: null,
            'dependsOn' => $this->dependsOn,
            ...$this->meta,
        ], fn ($v): bool => $v !== null);
    }
}

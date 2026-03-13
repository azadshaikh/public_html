<?php

declare(strict_types=1);

namespace App\Scaffold;

use BackedEnum;
use UnitEnum;

/**
 * Column - Fluent column builder for scaffold definitions
 *
 * @example
 * Column::make('name')->label('Name')->sortable()->searchable()
 * Column::make('status')->label('Status')->badge()->filterable(['active', 'pending', 'inactive'])
 * Column::make('created_at')->label('Created')->date()->sortable()
 */
class Column
{
    public string $key;

    public string $label;

    public string $type = 'text';

    public bool $sortable = false;

    /**
     * Column to actually sort on (for computed/virtual columns)
     * When null, uses the column key itself
     */
    public ?string $sortColumn = null;

    public bool $searchable = false;

    /**
     * Columns to actually search on (for computed/virtual columns)
     * When null, uses the column key itself
     */
    public ?array $searchColumns = null;

    /**
     * Whether this column uses a full-text index for searching.
     * When true, search will use whereFullText() instead of ILIKE.
     */
    public bool $fulltext = false;

    public ?array $filterOptions = null;

    public ?string $filterType = null;

    public ?string $align = null;

    public ?string $class = null;

    public ?string $template = null;

    public bool $visible = true;

    public bool $exportable = true;

    public ?int $width = null;

    public ?string $widthCss = null;

    /**
     * Map of value → badge variant name for badge-type columns.
     * e.g. ['active' => 'success', 'banned' => 'danger']
     *
     * @var array<string, string>|null
     */
    public ?array $badgeVariants = null;

    public array $meta = [];

    /**
     * Create a new column
     */
    public static function make(string $key): self
    {
        $column = new self;
        $column->key = $key;
        $column->label = str($key)->headline()->toString();

        return $column;
    }

    /**
     * Set column label
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set column type
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Make column sortable
     *
     * @param  bool|string  $sortable  True to sort on column key, or string of actual DB column
     */
    public function sortable(bool|string $sortable = true): self
    {
        if (is_string($sortable)) {
            $this->sortable = true;
            $this->sortColumn = $sortable;
        } else {
            $this->sortable = $sortable;
        }

        return $this;
    }

    /**
     * Make column searchable
     *
     * @param  bool|array  $searchable  True to search on column key, or array of actual DB columns
     */
    public function searchable(bool|array $searchable = true): self
    {
        if (is_array($searchable)) {
            $this->searchable = true;
            $this->searchColumns = $searchable;
        } else {
            $this->searchable = $searchable;
        }

        return $this;
    }

    /**
     * Enable full-text search on this column.
     *
     * Requires a full-text index on the column (GIN index in PostgreSQL).
     * When enabled, search uses whereFullText() instead of ILIKE.
     */
    public function fulltext(): self
    {
        $this->searchable = true;
        $this->fulltext = true;

        return $this;
    }

    /**
     * Make column filterable
     *
     * @param  array|string|null  $options  Filter options or enum class
     */
    public function filterable(array|string|null $options = null): self
    {
        if (is_string($options) && enum_exists($options)) {
            // Convert enum to options array, using label() when available
            $this->filterOptions = collect($options::cases())
                ->mapWithKeys(function (UnitEnum $case): array {
                    $key = $case instanceof BackedEnum ? (string) $case->value : $case->name;
                    $label = method_exists($case, 'label') ? (string) $case->label() : $case->name;

                    return [$key => $label];
                })
                ->all();
        } elseif (is_array($options)) {
            $this->filterOptions = $options;
        } else {
            $this->filterType = 'text';
        }

        return $this;
    }

    /**
     * Set as badge type
     */
    public function badge(): self
    {
        $this->type = 'badge';

        return $this;
    }

    /**
     * Set badge variant mapping for badge-type columns.
     *
     * Accepts an enum class (reads badge() from each case) or
     * an associative array of value => variant name.
     *
     * Variant names should match the Badge component variants:
     * default, secondary, success, warning, info, danger, destructive, outline
     *
     * @param  array<string, string>|string  $variants  Enum class or value→variant map
     *
     * @example
     * Column::make('status')->badge()->badgeVariants(Status::class)
     * Column::make('type')->badge()->badgeVariants(['admin' => 'info', 'user' => 'secondary'])
     */
    public function badgeVariants(array|string $variants): self
    {
        if (is_string($variants) && enum_exists($variants)) {
            $this->badgeVariants = collect($variants::cases())
                ->filter(fn (UnitEnum $case): bool => method_exists($case, 'badge'))
                ->mapWithKeys(function (UnitEnum $case): array {
                    $key = $case instanceof BackedEnum ? (string) $case->value : $case->name;

                    return [$key => $case->badge()];
                })
                ->all();
        } else {
            $this->badgeVariants = $variants;
        }

        $this->type = 'badge';

        return $this;
    }

    /**
     * Set as date type
     *
     * @deprecated DO NOT USE! Date formatting is done server-side in Resources.
     *             This method no longer triggers any client-side formatting.
     *             Format dates using app_date_time_format() in your Resource instead.
     */
    public function date(): self
    {
        @trigger_error('Column::date() is deprecated. Format dates server-side in your Resource using app_date_time_format().', E_USER_DEPRECATED);
        $this->type = 'date';

        return $this;
    }

    /**
     * Set as datetime type
     *
     * @deprecated DO NOT USE! Date/time formatting is done server-side in Resources.
     *             This method no longer triggers any client-side formatting.
     *             Format datetimes using app_date_time_format() in your Resource instead.
     */
    public function datetime(): self
    {
        @trigger_error('Column::datetime() is deprecated. Format datetimes server-side in your Resource using app_date_time_format().', E_USER_DEPRECATED);
        $this->type = 'datetime';

        return $this;
    }

    /**
     * Set as time type
     *
     * @deprecated DO NOT USE! Time formatting is done server-side in Resources.
     *             This method no longer triggers any client-side formatting.
     *             Format times using app_date_time_format() in your Resource instead.
     */
    public function time(): self
    {
        @trigger_error('Column::time() is deprecated. Format times server-side in your Resource using app_date_time_format().', E_USER_DEPRECATED);
        $this->type = 'time';

        return $this;
    }

    /**
     * Set as boolean type
     */
    public function boolean(): self
    {
        $this->type = 'boolean';

        return $this;
    }

    /**
     * Set as checkbox type (for bulk selection)
     */
    public function checkbox(): self
    {
        $this->type = 'checkbox';
        $this->sortable = false;
        $this->searchable = false;
        $this->exportable = false;

        return $this;
    }

    /**
     * Set as actions column type
     */
    public function actions(): self
    {
        $this->type = 'actions';
        $this->sortable = false;
        $this->searchable = false;
        $this->exportable = false;
        $this->class = 'datagrid-action-column';

        return $this;
    }

    /**
     * Set as currency type
     */
    public function currency(string $symbol = '$'): self
    {
        $this->type = 'currency';
        $this->meta['currency'] = $symbol;

        return $this;
    }

    /**
     * Set as link type
     */
    public function link(string $hrefKey = 'url'): self
    {
        $this->type = 'link';
        $this->meta['hrefKey'] = $hrefKey;

        return $this;
    }

    /**
     * Set as image type
     */
    public function image(int $size = 40): self
    {
        $this->type = 'image';
        $this->meta['size'] = $size;

        return $this;
    }

    /**
     * Set custom template
     */
    public function template(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Set text alignment
     */
    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    /**
     * Center align
     */
    public function center(): self
    {
        return $this->align('center');
    }

    /**
     * Right align
     */
    public function right(): self
    {
        return $this->align('end');
    }

    /**
     * Set CSS class
     */
    public function class(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Set column width (integer pixels or CSS string)
     */
    public function width(int|string $width): self
    {
        if (is_int($width)) {
            $this->width = $width;
        } else {
            $this->widthCss = $width;
        }

        return $this;
    }

    /**
     * Hide column from table
     */
    public function hidden(bool $hidden = true): self
    {
        $this->visible = ! $hidden;

        return $this;
    }

    /**
     * Exclude from export
     */
    public function excludeFromExport(bool $exclude = true): self
    {
        $this->exportable = ! $exclude;

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
            'type' => $this->type,
            'sortable' => $this->sortable ?: null,
            'searchable' => $this->searchable ?: null,
            'filterOptions' => $this->filterOptions,
            'filterType' => $this->filterType,
            'align' => $this->align,
            'class' => $this->class,
            'template' => $this->template,
            'visible' => $this->visible ? null : false,
            'exportable' => $this->exportable ? null : false,
            'width' => $this->width ?? $this->widthCss,
            'badgeVariants' => $this->badgeVariants,
            ...$this->meta,
        ], fn ($v): bool => $v !== null);
    }
}

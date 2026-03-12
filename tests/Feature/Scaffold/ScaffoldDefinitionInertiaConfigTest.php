<?php

declare(strict_types=1);

namespace Tests\Feature\Scaffold;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Tests\TestCase;

/**
 * Stub definition for testing toInertiaConfig().
 *
 * Exercises every builder: columns, filters, actions, status tabs,
 * and settings — without relying on any real model or service.
 */
class StubDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'Widget';

    protected string $entityPlural = 'Widgets';

    protected string $routePrefix = 'app.widgets';

    protected string $permissionPrefix = 'widgets';

    protected int $perPage = 25;

    protected ?string $defaultSort = 'name';

    protected string $defaultSortDirection = 'asc';

    protected bool $enableBulkActions = true;

    protected bool $enableExport = true;

    public function getModelClass(): string
    {
        return 'App\\Models\\Widget'; // no real model needed
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->excludeFromExport(),
            Column::make('name')->label('Name')->sortable()->searchable(),
            Column::make('status')->label('Status')->template('badge')->sortable(),
            Column::make('hidden_col')->label('Hidden')->hidden(), // invisible column
            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status')
                ->label('Status')
                ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                ->placeholder('All Statuses'),
            Filter::dateRange('created_at')
                ->label('Created Date'),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route('app.widgets.show')
                ->forRow(),
            Action::make('delete')
                ->label('Delete')
                ->icon('ri-delete-bin-line')
                ->route('app.widgets.destroy')
                ->method('DELETE')
                ->danger()
                ->confirm('Delete this widget?')
                ->confirmBulk('Delete {count} widgets?')
                ->forBoth(),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-dashboard-line')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
        ];
    }
}

class ScaffoldDefinitionInertiaConfigTest extends TestCase
{
    private StubDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->definition = new StubDefinition;
    }

    // =========================================================================
    // TOP-LEVEL STRUCTURE
    // =========================================================================

    public function test_inertia_config_has_required_top_level_keys(): void
    {
        $config = $this->definition->toInertiaConfig();

        $this->assertArrayHasKey('columns', $config);
        $this->assertArrayHasKey('filters', $config);
        $this->assertArrayHasKey('actions', $config);
        $this->assertArrayHasKey('statusTabs', $config);
        $this->assertArrayHasKey('settings', $config);

        // All top-level values are arrays
        foreach ($config as $key => $value) {
            $this->assertIsArray($value, "Top-level key '{$key}' must be an array.");
        }
    }

    // =========================================================================
    // COLUMNS
    // =========================================================================

    public function test_columns_excludes_hidden_columns(): void
    {
        $columns = $this->definition->toInertiaConfig()['columns'];

        $keys = array_column($columns, 'key');
        $this->assertContains('name', $keys);
        $this->assertContains('status', $keys);
        $this->assertNotContains('hidden_col', $keys, 'Hidden columns must be excluded.');
    }

    public function test_columns_preserve_builder_properties(): void
    {
        $columns = collect($this->definition->toInertiaConfig()['columns']);

        $nameCol = $columns->firstWhere('key', 'name');
        $this->assertNotNull($nameCol);
        $this->assertSame('Name', $nameCol['label']);
        $this->assertTrue($nameCol['sortable']);
        $this->assertTrue($nameCol['searchable']);

        $statusCol = $columns->firstWhere('key', 'status');
        $this->assertNotNull($statusCol);
        $this->assertSame('badge', $statusCol['template']);
        $this->assertTrue($statusCol['sortable']);
    }

    public function test_columns_are_sequentially_indexed(): void
    {
        $columns = $this->definition->toInertiaConfig()['columns'];

        $this->assertSame(array_values($columns), $columns, 'Columns must be a sequential (values) array.');
    }

    // =========================================================================
    // FILTERS
    // =========================================================================

    public function test_filters_include_all_defined(): void
    {
        $filters = $this->definition->toInertiaConfig()['filters'];

        $this->assertCount(2, $filters);

        $keys = array_column($filters, 'key');
        $this->assertContains('status', $keys);
        $this->assertContains('created_at', $keys);
    }

    public function test_filter_properties_are_exported(): void
    {
        $filters = collect($this->definition->toInertiaConfig()['filters']);

        $statusFilter = $filters->firstWhere('key', 'status');
        $this->assertNotNull($statusFilter);
        $this->assertSame('select', $statusFilter['type']);
        $this->assertSame('Status', $statusFilter['label']);
        $this->assertSame('All Statuses', $statusFilter['placeholder']);
        $this->assertNotEmpty($statusFilter['options']);

        $dateFilter = $filters->firstWhere('key', 'created_at');
        $this->assertNotNull($dateFilter);
        $this->assertSame('date_range', $dateFilter['type']);
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    public function test_actions_are_exported(): void
    {
        $actions = $this->definition->toInertiaConfig()['actions'];

        $this->assertCount(2, $actions);

        $keys = array_column($actions, 'key');
        $this->assertContains('show', $keys);
        $this->assertContains('delete', $keys);
    }

    public function test_action_properties_are_exported(): void
    {
        $actions = collect($this->definition->toInertiaConfig()['actions']);

        $delete = $actions->firstWhere('key', 'delete');
        $this->assertNotNull($delete);
        $this->assertSame('Delete', $delete['label']);
        $this->assertSame('DELETE', $delete['method']);
        $this->assertSame('danger', $delete['variant']);
        $this->assertSame('Delete this widget?', $delete['confirm']);
        $this->assertSame('Delete {count} widgets?', $delete['confirmBulk']);
        $this->assertSame('both', $delete['scope']);
    }

    // =========================================================================
    // STATUS TABS
    // =========================================================================

    public function test_status_tabs_are_exported(): void
    {
        $tabs = $this->definition->toInertiaConfig()['statusTabs'];

        $this->assertCount(2, $tabs);

        $allTab = collect($tabs)->firstWhere('key', 'all');
        $this->assertNotNull($allTab);
        $this->assertTrue($allTab['isDefault']);

        $activeTab = collect($tabs)->firstWhere('key', 'active');
        $this->assertNotNull($activeTab);
        $this->assertSame('active', $activeTab['value']);
        $this->assertSame('success', $activeTab['color']);
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    public function test_settings_reflect_definition_properties(): void
    {
        $settings = $this->definition->toInertiaConfig()['settings'];

        $this->assertSame(25, $settings['perPage']);
        $this->assertSame('name', $settings['defaultSort']);
        $this->assertSame('asc', $settings['defaultDirection']);
        $this->assertTrue($settings['enableBulkActions']);
        $this->assertTrue($settings['enableExport']);
        $this->assertFalse($settings['hasNotes']);
        $this->assertSame('Widget', $settings['entityName']);
        $this->assertSame('Widgets', $settings['entityPlural']);
        $this->assertSame('app.widgets', $settings['routePrefix']);
        $this->assertSame('status', $settings['statusField']);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_empty_filters_and_tabs_produce_empty_arrays(): void
    {
        $minimal = new class extends ScaffoldDefinition
        {
            public function getModelClass(): string
            {
                return 'App\\Models\\Stub';
            }

            public function columns(): array
            {
                return [Column::make('id')->label('ID')];
            }
        };

        $config = $minimal->toInertiaConfig();

        $this->assertSame([], $config['filters']);
        $this->assertCount(1, $config['columns']);
        // Default statusTabs returns ['all']
        $this->assertCount(1, $config['statusTabs']);
    }
}

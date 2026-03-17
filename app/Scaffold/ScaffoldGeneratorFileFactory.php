<?php

declare(strict_types=1);

namespace App\Scaffold;

use Illuminate\Support\Str;

class ScaffoldGeneratorFileFactory
{
    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<string, array{path: string, contents: string}>
     */
    public function make(array $blueprint): array
    {
        $context = $this->buildContext($blueprint);

        $files = [
            'model' => $this->makeFile($blueprint, 'model', $this->renderModel($context)),
            'request' => $this->makeFile($blueprint, 'request', $this->renderRequest($context)),
            'definition' => $this->makeFile($blueprint, 'definition', $this->renderDefinition($context)),
            'controller' => $this->makeFile($blueprint, 'controller', $this->renderController($context)),
            'service' => $this->makeFile($blueprint, 'service', $this->renderService($context)),
            'page:index' => $this->makeFile($blueprint, 'page:index', $this->renderIndexPage($context)),
            'page:create' => $this->makeFile($blueprint, 'page:create', $this->renderCreatePage($context)),
            'page:edit' => $this->makeFile($blueprint, 'page:edit', $this->renderEditPage($context)),
            'page:show' => $this->makeFile($blueprint, 'page:show', $this->renderShowPage($context)),
            'form-component' => $this->makeFile($blueprint, 'form-component', $this->renderFormComponent($context)),
            'factory' => $this->makeFile($blueprint, 'factory', $this->renderFactory($context)),
            'seeder' => $this->makeFile($blueprint, 'seeder', $this->renderSeeder($context)),
            'migration' => $this->makeFile($blueprint, 'migration', $this->renderMigration($context)),
            'crud-test' => $this->makeTestFile($blueprint, 'crud', $this->renderCrudTest($context)),
        ];

        return collect($files)
            ->filter(fn (mixed $file): bool => is_array($file) && is_string($file['path'] ?? null) && $file['path'] !== '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<string, string>
     */
    private function buildContext(array $blueprint): array
    {
        $entity = (string) ($blueprint['generator']['entity'] ?? 'Resource');
        $module = $blueprint['generator']['module'] ?? null;
        $resourceSlug = (string) ($blueprint['generator']['resource_slug'] ?? Str::of($entity)->pluralStudly()->snake()->replace('_', '-'));
        $singularSlug = Str::of($entity)->snake()->replace('_', '-')->toString();
        $singularSnake = Str::of($entity)->snake()->toString();
        $tableName = str_replace('-', '_', $resourceSlug);
        $studlyPlural = Str::pluralStudly($entity);
        $entityName = (string) ($blueprint['entity_name'] ?? Str::headline($entity));
        $entityPlural = (string) ($blueprint['entity_plural'] ?? Str::headline($studlyPlural));
        $inertiaPagePrefix = (string) ($blueprint['inertia_page_prefix'] ?? Str::of($resourceSlug)->replace('-', '/')->toString());
        $pageSegments = array_values(array_filter(explode('/', $inertiaPagePrefix)));
        $formImportPrefix = str_repeat('../', count($pageSegments) + 1);
        $formImportPath = $formImportPrefix.'components/'.$resourceSlug.'/'.$singularSlug.'-form';
        $breadcrumbsIndexRoute = $this->routeCall((string) $blueprint['route_names']['index'], ['status' => 'all']);
        $indexRoute = (string) ($blueprint['route_names']['index'] ?? 'app.'.Str::of($resourceSlug)->replace('-', '.')->toString().'.index');
        $createRoute = (string) ($blueprint['route_names']['create'] ?? '');
        $storeRoute = (string) ($blueprint['route_names']['store'] ?? '');
        $showRoute = (string) ($blueprint['route_names']['show'] ?? '');
        $editRoute = (string) ($blueprint['route_names']['edit'] ?? '');
        $updateRoute = (string) ($blueprint['route_names']['update'] ?? '');
        $addAbility = (string) ($blueprint['ability_map']['add'] ?? '');
        $modelClass = (string) ($blueprint['classes']['model'] ?? '');
        $requestClass = (string) ($blueprint['classes']['request'] ?? '');
        $definitionClass = (string) ($blueprint['classes']['definition'] ?? '');
        $controllerClass = (string) ($blueprint['classes']['controller'] ?? '');
        $serviceClass = (string) ($blueprint['classes']['service'] ?? '');
        $testClass = $entity.'CrudTest';
        $modelVariable = Str::camel($entity);

        return [
            'entity' => $entity,
            'entity_name' => $entityName,
            'entity_name_lower' => Str::lower($entityName),
            'entity_plural' => $entityPlural,
            'module' => is_string($module) && $module !== '' ? $module : null,
            'resource_slug' => $resourceSlug,
            'singular_slug' => $singularSlug,
            'singular_slug_placeholder' => $singularSlug,
            'singular_snake' => $singularSnake,
            'table_name' => $tableName,
            'studly_plural' => $studlyPlural,
            'route_prefix' => (string) ($blueprint['route_prefix'] ?? ''),
            'permission_prefix' => (string) ($blueprint['permission_prefix'] ?? ''),
            'inertia_page_prefix' => $inertiaPagePrefix,
            'form_import_path' => $formImportPath,
            'index_route' => $indexRoute,
            'create_route' => $createRoute,
            'store_route' => $storeRoute,
            'show_route' => $showRoute,
            'edit_route' => $editRoute,
            'update_route' => $updateRoute,
            'breadcrumbs_index_route' => $breadcrumbsIndexRoute,
            'add_ability' => $addAbility,
            'model_class' => $modelClass,
            'model_namespace' => $this->classNamespace($modelClass),
            'model_basename' => $this->classBasename($modelClass),
            'request_class' => $requestClass,
            'request_namespace' => $this->classNamespace($requestClass),
            'request_basename' => $this->classBasename($requestClass),
            'definition_class' => $definitionClass,
            'definition_namespace' => $this->classNamespace($definitionClass),
            'definition_basename' => $this->classBasename($definitionClass),
            'controller_class' => $controllerClass,
            'controller_namespace' => $this->classNamespace($controllerClass),
            'controller_basename' => $this->classBasename($controllerClass),
            'service_class' => $serviceClass,
            'service_namespace' => $this->classNamespace($serviceClass),
            'service_basename' => $this->classBasename($serviceClass),
            'test_class' => $testClass,
            'test_namespace' => $this->resolveTestNamespace(is_string($module) ? $module : null),
            'model_variable' => $modelVariable,
            'model_variable_php' => '$'.$modelVariable,
            'service_property' => '$'.$modelVariable.'Service',
            'this_service_property' => '$this->'.$modelVariable.'Service',
            'php_this' => '$this',
            'php_table' => '$table',
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array{path: string, contents: string}|null
     */
    private function makeFile(array $blueprint, string $type, string $contents): ?array
    {
        $path = $blueprint['file_paths'][$type] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return [
            'path' => $path,
            'contents' => $contents,
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array{path: string, contents: string}|null
     */
    private function makeTestFile(array $blueprint, string $type, string $contents): ?array
    {
        $path = $blueprint['test_paths'][$type] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return [
            'path' => $path,
            'contents' => $contents,
        ];
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderModel(array $context): string
    {
        $factoryImport = $context['module'] === null
            ? 'Database\\Factories\\'.$context['model_basename'].'Factory'
            : 'Modules\\'.$context['module'].'\\Database\\Factories\\'.$context['model_basename'].'Factory';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$context['model_namespace']};

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use {$factoryImport};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {$context['model_basename']} extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected static function newFactory(): Factory
    {
        return {$context['model_basename']}Factory::new();
    }

    protected \$fillable = [
        'name',
        'slug',
        'description',
        'status',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderRequest(array $context): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$context['request_namespace']};

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use {$context['definition_class']};

class {$context['request_basename']} extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', {$context['php_this']}->uniqueRule('slug')],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new {$context['definition_basename']};
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderDefinition(array $context): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$context['definition_namespace']};

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use {$context['request_class']};
use {$context['model_class']};

class {$context['definition_basename']} extends ScaffoldDefinition
{
    protected string \$routePrefix = '{$context['route_prefix']}';

    protected string \$permissionPrefix = '{$context['permission_prefix']}';

    protected bool \$expectsGeneratedRegistrationMerges = true;

    protected ?string \$statusField = 'status';

    public function getModelClass(): string
    {
        return {$context['model_basename']}::class;
    }

    public function getRequestClass(): ?string
    {
        return {$context['request_basename']}::class;
    }

    public function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->searchable(),
            Column::make('slug')->label('Slug')->sortable()->searchable(),
            Column::make('status')->label('Status')->badge()->sortable(),
            Column::make('created_at')->label('Created')->sortable(),
            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('90px'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('search')
                ->label('Search')
                ->placeholder('Search...'),
            Filter::select('status')
                ->label('Status')
                ->placeholder('All statuses')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ]),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('warning'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderController(array $context): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$context['controller_namespace']};

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use {$context['definition_class']};
use {$context['model_class']};
use {$context['service_class']};

class {$context['controller_basename']} extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly {$context['service_basename']} {$context['service_property']}) {}

    public static function middleware(): array
    {
        return (new {$context['definition_basename']})->getMiddleware();
    }

    protected function service(): {$context['service_basename']}
    {
        return {$context['this_service_property']};
    }

    protected function inertiaPage(): string
    {
        return '{$context['inertia_page_prefix']}';
    }

    protected function getFormViewData(Model \$model): array
    {
        /** @var {$context['model_basename']} {$context['model_variable_php']} */
        {$context['model_variable_php']} = \$model;

        return [
            'initialValues' => [
                'name' => (string) ({$context['model_variable_php']}->getAttribute('name') ?? ''),
                'slug' => (string) ({$context['model_variable_php']}->getAttribute('slug') ?? ''),
                'description' => (string) ({$context['model_variable_php']}->getAttribute('description') ?? ''),
                'status' => (string) ({$context['model_variable_php']}->getAttribute('status') ?? 'active'),
            ],
        ];
    }

    protected function transformModelForEdit(Model \$model): array
    {
        /** @var {$context['model_basename']} {$context['model_variable_php']} */
        {$context['model_variable_php']} = \$model;

        return [
            'id' => {$context['model_variable_php']}->getKey(),
            'name' => (string) {$context['model_variable_php']}->getAttribute('name'),
            'slug' => (string) {$context['model_variable_php']}->getAttribute('slug'),
            'description' => (string) ({$context['model_variable_php']}->getAttribute('description') ?? ''),
            'status' => (string) ({$context['model_variable_php']}->getAttribute('status') ?? 'active'),
        ];
    }

    protected function transformModelForShow(Model \$model): array
    {
        /** @var {$context['model_basename']} {$context['model_variable_php']} */
        {$context['model_variable_php']} = \$model;

        return [
            'id' => {$context['model_variable_php']}->getKey(),
            'name' => (string) {$context['model_variable_php']}->getAttribute('name'),
            'slug' => (string) {$context['model_variable_php']}->getAttribute('slug'),
            'description' => (string) ({$context['model_variable_php']}->getAttribute('description') ?? ''),
            'status' => (string) ({$context['model_variable_php']}->getAttribute('status') ?? 'active'),
            'created_at' => app_date_time_format({$context['model_variable_php']}->created_at, 'datetime'),
            'updated_at' => app_date_time_format({$context['model_variable_php']}->updated_at, 'datetime'),
        ];
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderService(array $context): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$context['service_namespace']};

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Support\Str;
use {$context['definition_class']};

class {$context['service_basename']} implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new {$context['definition_basename']};
    }

    protected function prepareCreateData(array \$data): array
    {
        return {$context['php_this']}->prepareData(\$data);
    }

    protected function prepareUpdateData(array \$data): array
    {
        return {$context['php_this']}->prepareData(\$data);
    }

    private function prepareData(array \$data): array
    {
        return [
            'name' => \$data['name'] ?? null,
            'slug' => isset(\$data['slug']) && \$data['slug'] !== ''
                ? Str::slug((string) \$data['slug'])
                : Str::slug((string) (\$data['name'] ?? '')),
            'description' => \$data['description'] ?? null,
            'status' => \$data['status'] ?? 'active',
            'metadata' => \$data['metadata'] ?? null,
        ];
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderIndexPage(array $context): string
    {
        return <<<TSX
import { Link, usePage } from '@inertiajs/react';
import { ListChecksIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldBulkActions, buildScaffoldDatagridState, mapScaffoldRowActions } from '@/lib/scaffold-datagrid';
import type { ScaffoldIndexPageProps, ScaffoldRowActionPayload } from '@/types/scaffold';
import type { BreadcrumbItem } from '@/types';

type AuthPageProps = {
    auth?: {
        abilities?: Record<string, boolean>;
    };
};

type GeneratedColumnConfig = {
    key: string;
    label?: string;
    sortable?: boolean;
    sortColumn?: string | null;
    type?: string;
    visible?: boolean;
};

type ScaffoldRow = {
    id: number | string;
    actions?: Record<string, ScaffoldRowActionPayload> | ScaffoldRowActionPayload[];
    [key: string]: unknown;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: '{$context['entity_plural']}', href: {$context['breadcrumbs_index_route']} },
];

export default function {$context['studly_plural']}Index({ config, rows, filters, statistics }: ScaffoldIndexPageProps<ScaffoldRow>) {
    const page = usePage<AuthPageProps>();
    const canCreate = Boolean(page.props.auth?.abilities?.['{$context['add_ability']}']);
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(
        config,
        filters,
        statistics,
        { searchPlaceholder: 'Search {$context['entity_plural']}...' },
    );

    const columns: DatagridColumn<ScaffoldRow>[] = ((config.columns ?? []) as GeneratedColumnConfig[])
        .filter((column) => column.visible !== false && column.key !== '_bulk_select' && column.key !== '_actions')
        .map((column) => ({
            key: column.key,
            header: column.label ?? column.key,
            sortable: Boolean(column.sortable),
            sortKey: column.sortColumn ?? undefined,
            type: column.type === 'badge' ? 'badge' : undefined,
        }));

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="{$context['entity_plural']}"
            description="Manage {$context['entity_plural']} using the generated scaffold datagrid."
            headerActions={
                canCreate ? (
                    <Button asChild>
                        <Link href={route('{$context['create_route']}')}>
                            <PlusIcon data-icon="inline-start" />
                            Add {$context['entity_name']}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('{$context['index_route']}', { status: currentStatus })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(row) => row.id}
                rowActions={(row) => mapScaffoldRowActions(row.actions)}
                bulkActions={buildScaffoldBulkActions(config.actions, {
                    routePrefix: config.settings.routePrefix,
                    currentStatus,
                })}
                empty={{
                    icon: <ListChecksIcon className="size-5" />,
                    title: 'No {$context['entity_plural']} found',
                    description: 'Create your first {$context['entity_name']} to start using this scaffold.',
                }}
                sorting={sorting}
                perPage={perPage}
                title="{$context['entity_plural']}"
                description="Generated from the scaffold runtime contract."
            />
        </AppLayout>
    );
}
TSX;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderCreatePage(array $context): string
    {
        return <<<TSX
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {$context['entity']}Form, { type {$context['entity']}FormValues } from '{$context['form_import_path']}';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: '{$context['entity_plural']}', href: {$context['breadcrumbs_index_route']} },
    { title: 'Create', href: route('{$context['create_route']}') },
];

type {$context['studly_plural']}CreatePageProps = {
    initialValues: {$context['entity']}FormValues;
};

export default function {$context['studly_plural']}Create({ initialValues }: {$context['studly_plural']}CreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create {$context['entity_name']}"
            description="Add a new {$context['entity_name']} using the generated scaffold form."
        >
            <{$context['entity']}Form mode="create" initialValues={initialValues} />
        </AppLayout>
    );
}
TSX;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderEditPage(array $context): string
    {
        return <<<TSX
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {$context['entity']}Form, { type {$context['entity']}FormValues } from '{$context['form_import_path']}';

type {$context['entity']}Record = {
    id: number | string;
    name: string;
    slug: string;
    description?: string | null;
    status: string;
};

type {$context['studly_plural']}EditPageProps = {
    {$context['model_variable']}: {$context['entity']}Record;
    initialValues: {$context['entity']}FormValues;
};

export default function {$context['studly_plural']}Edit({ {$context['model_variable']}, initialValues }: {$context['studly_plural']}EditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: '{$context['entity_plural']}', href: {$context['breadcrumbs_index_route']} },
        { title: {$context['model_variable']}.name, href: route('{$context['show_route']}', {$context['model_variable']}.id) },
        { title: 'Edit', href: route('{$context['edit_route']}', {$context['model_variable']}.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={'Edit ' + {$context['model_variable']}.name}
            description="Update the generated scaffold fields and save your changes."
        >
            <{$context['entity']}Form mode="edit" {$context['model_variable']}={{$context['model_variable']}} initialValues={initialValues} />
        </AppLayout>
    );
}
TSX;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderShowPage(array $context): string
    {
        return <<<TSX
import { Link } from '@inertiajs/react';
import { PencilIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type {$context['entity']}Detail = {
    id: number | string;
    name: string;
    slug: string;
    description?: string | null;
    status: string;
    created_at?: string | null;
    updated_at?: string | null;
};

type {$context['studly_plural']}ShowPageProps = {
    {$context['model_variable']}: {$context['entity']}Detail;
};

export default function {$context['studly_plural']}Show({ {$context['model_variable']} }: {$context['studly_plural']}ShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: '{$context['entity_plural']}', href: {$context['breadcrumbs_index_route']} },
        { title: {$context['model_variable']}.name, href: route('{$context['show_route']}', {$context['model_variable']}.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={{$context['model_variable']}.name}
            description="Review the generated scaffold detail view for this resource."
            headerActions={
                <Button asChild>
                    <Link href={route('{$context['edit_route']}', {$context['model_variable']}.id)}>
                        <PencilIcon data-icon="inline-start" />
                        Edit {$context['entity_name']}
                    </Link>
                </Button>
            }
        >
            <Card>
                <CardHeader>
                    <CardTitle>{$context['entity_name']} details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-1">
                            <dt className="text-sm font-medium text-muted-foreground">Name</dt>
                            <dd className="text-sm text-foreground">{{$context['model_variable']}.name}</dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-sm font-medium text-muted-foreground">Slug</dt>
                            <dd className="text-sm text-foreground">{{$context['model_variable']}.slug}</dd>
                        </div>
                        <div className="space-y-1 md:col-span-2">
                            <dt className="text-sm font-medium text-muted-foreground">Description</dt>
                            <dd className="text-sm text-foreground">{{$context['model_variable']}.description || '—'}</dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-sm font-medium text-muted-foreground">Status</dt>
                            <dd className="text-sm text-foreground">{{$context['model_variable']}.status}</dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-sm font-medium text-muted-foreground">Updated</dt>
                            <dd className="text-sm text-foreground">{{$context['model_variable']}.updated_at || '—'}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
TSX;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderFormComponent(array $context): string
    {
        return <<<TSX
import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';

export type {$context['entity']}FormValues = {
    name: string;
    slug: string;
    description: string;
    status: string;
};

type {$context['entity']}Record = {
    id: number | string;
    name: string;
};

type {$context['entity']}FormProps = {
    mode: 'create' | 'edit';
    {$context['model_variable']}?: {$context['entity']}Record;
    initialValues: {$context['entity']}FormValues;
};

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

export default function {$context['entity']}Form({ mode, {$context['model_variable']}, initialValues }: {$context['entity']}FormProps) {
    const form = useAppForm<{$context['entity']}FormValues>({
        defaults: initialValues,
        rememberKey: mode === 'create'
            ? '{$context['route_prefix']}.create.form'
            : '{$context['route_prefix']}.edit.' + ({$context['model_variable']}?.id ?? 'new'),
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Name')],
            slug: [formValidators.required('Slug')],
            status: [formValidators.required('Status')],
        },
    });

    const submitUrl = mode === 'create'
        ? route('{$context['store_route']}')
        : route('{$context['update_route']}', {$context['model_variable']}!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? '{$context['entity_name']} created' : '{$context['entity_name']} updated',
                description: mode === 'create'
                    ? 'The {$context['entity_name']} has been created successfully.'
                    : 'The {$context['entity_name']} has been updated successfully.',
            },
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <Card>
                <CardHeader>
                    <CardTitle>{$context['entity_name']} details</CardTitle>
                    <CardDescription>Capture the core fields for this generated scaffold resource.</CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup>
                        <Field data-invalid={form.invalid('name') || undefined}>
                            <FieldLabel htmlFor="name">Name</FieldLabel>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) => form.setField('name', event.target.value)}
                                onBlur={() => form.touch('name')}
                                aria-invalid={form.invalid('name') || undefined}
                                placeholder="{$context['entity_name']} name"
                            />
                            <FieldError>{form.error('name')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('slug') || undefined}>
                            <FieldLabel htmlFor="slug">Slug</FieldLabel>
                            <Input
                                id="slug"
                                value={form.data.slug}
                                onChange={(event) => form.setField('slug', event.target.value)}
                                onBlur={() => form.touch('slug')}
                                aria-invalid={form.invalid('slug') || undefined}
                                placeholder="{$context['singular_slug_placeholder']}"
                            />
                            <FieldError>{form.error('slug')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('description') || undefined}>
                            <FieldLabel htmlFor="description">Description</FieldLabel>
                            <Textarea
                                id="description"
                                rows={5}
                                value={form.data.description}
                                onChange={(event) => form.setField('description', event.target.value)}
                                onBlur={() => form.touch('description')}
                                aria-invalid={form.invalid('description') || undefined}
                                placeholder="Add internal context for this {$context['entity_name_lower']}..."
                            />
                            <FieldError>{form.error('description')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('status') || undefined}>
                            <FieldLabel htmlFor="status">Status</FieldLabel>
                            <NativeSelect
                                id="status"
                                value={form.data.status}
                                onChange={(event) => form.setField('status', event.target.value)}
                                onBlur={() => form.touch('status')}
                                aria-invalid={form.invalid('status') || undefined}
                            >
                                {statusOptions.map((option) => (
                                    <NativeSelectOption key={option.value} value={option.value}>
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <FieldError>{form.error('status')}</FieldError>
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('{$context['index_route']}', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to {$context['entity_plural']}
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create {$context['entity_name']}' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
TSX;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderFactory(array $context): string
    {
        $factoryNamespace = $context['module'] === null
            ? 'Database\\Factories'
            : 'Modules\\'.$context['module'].'\\Database\\Factories';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$factoryNamespace};

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use {$context['model_class']};

/**
 * @extends Factory<{$context['model_basename']}>
 */
class {$context['model_basename']}Factory extends Factory
{
    protected \$model = {$context['model_basename']}::class;

    public function definition(): array
    {
        \$name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title(\$name),
            'slug' => Str::slug(\$name),
            'description' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['active', 'inactive']),
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return {$context['php_this']}->state(['status' => 'active']);
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderSeeder(array $context): string
    {
        $seederNamespace = $context['module'] === null
            ? 'Database\\Seeders'
            : 'Modules\\'.$context['module'].'\\Database\\Seeders';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$seederNamespace};

use Illuminate\Database\Seeder;
use {$context['model_class']};

class {$context['model_basename']}Seeder extends Seeder
{
    public function run(): void
    {
        {$context['model_basename']}::factory()->count(5)->create();
    }
}
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderMigration(array $context): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$context['table_name']}', function (Blueprint {$context['php_table']}): void {
            {$context['php_table']}->id();
            {$context['php_table']}->string('name');
            {$context['php_table']}->string('slug')->unique();
            {$context['php_table']}->text('description')->nullable();
            {$context['php_table']}->string('status')->default('active')->index();
            {$context['php_table']}->json('metadata')->nullable();
            {$context['php_table']}->unsignedBigInteger('created_by')->nullable();
            {$context['php_table']}->unsignedBigInteger('updated_by')->nullable();
            {$context['php_table']}->unsignedBigInteger('deleted_by')->nullable();
            {$context['php_table']}->timestamps();
            {$context['php_table']}->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$context['table_name']}');
    }
};
PHP;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderCrudTest(array $context): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$context['test_namespace']};

use Tests\TestCase;

class {$context['test_class']} extends TestCase
{
    public function test_generated_scaffold_requires_domain_specific_follow_up(): void
    {
        {$context['php_this']}->markTestSkipped('Finish any module-specific permissions, seeders, and domain rules before enabling generated CRUD coverage.');
    }
}
PHP;
    }

    private function classNamespace(string $class): string
    {
        return (string) Str::beforeLast($class, '\\');
    }

    private function classBasename(string $class): string
    {
        return (string) Str::afterLast($class, '\\');
    }

    private function resolveTestNamespace(?string $module): string
    {
        if (is_string($module) && $module !== '') {
            return 'Modules\\'.$module.'\\Tests\\Feature';
        }

        return 'Tests\\Feature';
    }

    /**
     * @param  array<string, string|int>  $parameters
     */
    private function routeCall(string $routeName, array $parameters = []): string
    {
        if ($parameters === []) {
            return sprintf("route('%s')", $routeName);
        }

        $exports = collect($parameters)
            ->map(fn (string|int $value, string $key): string => sprintf("'%s' => '%s'", $key, (string) $value))
            ->implode(', ');

        return sprintf("route('%s', [%s])", $routeName, $exports);
    }
}

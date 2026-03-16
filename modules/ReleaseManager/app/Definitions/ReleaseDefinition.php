<?php

declare(strict_types=1);

namespace Modules\ReleaseManager\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\ReleaseManager\Http\Requests\ReleaseRequest;
use Modules\ReleaseManager\Models\Release;

class ReleaseDefinition extends ScaffoldDefinition
{
    public function __construct(
        private readonly string $type = 'application'
    ) {
        $this->routePrefix = 'releasemanager.releases';
        $this->permissionPrefix = 'releases';
        $this->statusField = 'status';
        $this->perPage = 10;
        $this->defaultSort = 'release_at';
        $this->defaultSortDirection = 'desc';
    }

    public function getModelClass(): string
    {
        return Release::class;
    }

    public function getRequestClass(): ?string
    {
        return ReleaseRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('version')
                ->label('Version')
                ->sortable()
                ->searchable(['version', 'package_identifier', 'change_log', 'release_link', 'file_name'])
                ->link('show_url')
                ->width('170px'),

            Column::make('package_identifier')
                ->label('Package')
                ->sortable()
                ->width('160px'),

            Column::make('version_type')
                ->label('Type')
                ->badge()
                ->sortable()
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->badge()
                ->sortable()
                ->width('140px'),

            Column::make('release_at')
                ->label('Release Date')
                ->sortable()
                ->width('140px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('140px'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('version_type')
                ->label('Version Type')
                ->options(config('releasemanager.version_types', []))
                ->placeholder('All Version Types'),

            Filter::dateRange('release_at')
                ->label('Release Date'),

            Filter::dateRange('created_at')
                ->label('Created Date'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ListCheck')
                ->color('primary')
                ->default(),

            StatusTab::make('published')
                ->label('Published')
                ->icon('Rocket')
                ->color('success')
                ->value('published'),

            StatusTab::make('draft')
                ->label('Draft')
                ->icon('FileText')
                ->color('warning')
                ->value('draft'),

            StatusTab::make('deprecate')
                ->label('Deprecate')
                ->icon('XCircle')
                ->color('secondary')
                ->value('deprecate'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('Trash2')
                ->color('danger'),
        ];
    }
}

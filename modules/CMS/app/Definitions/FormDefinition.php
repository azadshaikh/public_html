<?php

declare(strict_types=1);

namespace Modules\CMS\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\CMS\Http\Requests\FormRequest as CmsFormRequest;
use Modules\CMS\Models\Form;

class FormDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'Form';

    protected string $entityPlural = 'Forms';

    protected string $routePrefix = 'cms.form';

    protected string $permissionPrefix = 'cms_forms';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Form::class;
    }

    public function getRequestClass(): ?string
    {
        return CmsFormRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('title')
                ->label('Form')
                ->sortable()
                ->searchable(['title', 'slug', 'shortcode'])
                ->link('show_url')
                ->width('260px'),

            Column::make('template')
                ->label('Template')
                ->template('badge')
                ->sortable()
                ->width('160px'),

            Column::make('submissions_count')
                ->label('Submissions')
                ->sortable()
                ->right()
                ->width('120px'),

            Column::make('conversion_rate_display')
                ->label('Conversion')
                ->sortable('conversion_rate')
                ->right()
                ->width('120px'),

            Column::make('is_active')
                ->label('Active')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('140px'),

            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('80px'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('template')->label('Template')->placeholder('All Templates')->options([]),
            Filter::select('form_type')->label('Form Type')->placeholder('All Types')->options([]),
            Filter::select('is_active')->label('Active')->placeholder('All')->options([]),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('draft')->label('Draft')->icon('ri-file-line')->color('warning')->value('draft'),
            StatusTab::make('published')->label('Published')->icon('ri-checkbox-circle-line')->color('success')->value('published'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}

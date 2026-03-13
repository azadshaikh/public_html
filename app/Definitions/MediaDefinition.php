<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Models\CustomMedia;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

/**
 * MediaDefinition - Scaffold definition for Media Library V2
 *
 * Defines DataGrid configuration for the media library:
 * - Columns with thumbnail and file info
 * - Filters for file type, owner, date
 * - Status tabs (All, Trash)
 * - Actions (edit, delete, restore)
 */
class MediaDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.media-library';

    protected string $permissionPrefix = 'media';

    // Media uses soft deletes, not a status field
    protected ?string $statusField = null;

    // More items per page for gallery view
    protected int $perPage = 24;

    /**
     * Get the model class for this scaffold
     */
    public function getModelClass(): string
    {
        return CustomMedia::class;
    }

    /**
     * Define table columns for DataGrid
     */
    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('thumbnail_url')
                ->label('')
                ->template('thumbnail')
                ->width('80px')
                ->excludeFromExport(),

            Column::make('file_name')
                ->label('Name')
                ->sortable()
                ->searchable()
                ->link('edit_url'),

            Column::make('mime_type')
                ->label('Type')
                ->sortable()
                ->template('badge'),

            Column::make('human_readable_size')
                ->label('Size')
                ->sortable('size'),

            Column::make('created_at')
                ->label('Uploaded')
                ->sortable()
                ->template('datetime'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    /**
     * Define available filters for DataGrid
     */
    public function filters(): array
    {
        return [
            Filter::select('mime_type_category')
                ->label('File Type')
                ->options([
                    'image' => 'Images',
                    'video' => 'Videos',
                    'audio' => 'Audio',
                    'document' => 'Documents',
                ])
                ->placeholder('All Types'),

            Filter::select('created_by')
                ->label('Uploaded By')
                ->placeholder('All Users'),

            Filter::dateRange('created_at')
                ->label('Upload Date'),
        ];
    }

    /**
     * Define status tabs
     */
    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-image-line')
                ->color('primary')
                ->default(),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    /**
     * Define actions for media items
     *
     * Uses custom actions since media uses different routes than standard CRUD.
     */
    public function actions(): array
    {
        return [
            // Both row and bulk: Delete (move to trash)
            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->route('app.media.destroy')
                ->method('DELETE')
                ->confirm('Are you sure you want to move this file to trash?')
                ->confirmBulk('Move {count} files to trash?')
                ->permission('delete_media')
                ->hideOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Restore (only on trash)
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->success()
                ->route('app.media.restore')
                ->method('PATCH')
                ->confirm('Are you sure you want to restore this file?')
                ->confirmBulk('Restore {count} files?')
                ->permission('edit_media')
                ->showOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Permanent delete (only on trash)
            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->danger()
                ->route('app.media.destroy')
                ->method('DELETE')
                ->confirm('⚠️ This will permanently delete the file and cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} files? This cannot be undone!')
                ->permission('delete_media')
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }
}

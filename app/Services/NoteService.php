<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NoteType;
use App\Enums\NoteVisibility;
use App\Events\NoteCreated;
use App\Events\NoteDeleted;
use App\Events\NotePinned;
use App\Events\NoteUpdated;
use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * NoteService - Business logic for notes CRUD operations
 *
 * This service handles all note operations and dispatches events.
 *
 * @example
 * $noteService = app(NoteService::class);
 * $note = $noteService->create($website, ['content' => 'Note text']);
 */
class NoteService
{
    /**
     * Create a note for a model.
     *
     * @param  Model  $noteable  The model to attach the note to
     * @param  array  $data  Note data (content, type, visibility, metadata)
     */
    public function create(Model $noteable, array $data): Note
    {
        $note = Note::query()->create([
            'noteable_type' => $noteable::class,
            'noteable_id' => $noteable->getKey(),
            'content' => Note::sanitizeContent((string) $data['content']),
            'type' => $data['type'] ?? NoteType::Note,
            'visibility' => $data['visibility'] ?? NoteVisibility::Team,
            'metadata' => $data['metadata'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        event(new NoteCreated($note));

        return $note;
    }

    /**
     * Update a note.
     *
     * @param  Note  $note  The note to update
     * @param  array  $data  Updated data (content, visibility, metadata)
     */
    public function update(Note $note, array $data): Note
    {
        $note->update([
            'content' => array_key_exists('content', $data)
                ? Note::sanitizeContent((string) $data['content'])
                : $note->content,
            'visibility' => $data['visibility'] ?? $note->visibility,
            'metadata' => $data['metadata'] ?? $note->metadata,
            'updated_by' => auth()->id(),
        ]);

        event(new NoteUpdated($note));

        return $note->fresh();
    }

    /**
     * Delete a note (soft delete).
     */
    public function delete(Note $note): bool
    {
        $note->update(['deleted_by' => auth()->id()]);
        $deleted = $note->delete();

        if ($deleted) {
            event(new NoteDeleted($note));
        }

        return $deleted;
    }

    /**
     * Pin a note.
     */
    public function pin(Note $note): Note
    {
        $note->pin();
        event(new NotePinned($note, true));

        return $note->fresh();
    }

    /**
     * Unpin a note.
     */
    public function unpin(Note $note): Note
    {
        $note->unpin();
        event(new NotePinned($note, false));

        return $note->fresh();
    }

    /**
     * Toggle pin status.
     */
    public function togglePin(Note $note): Note
    {
        $wasPinned = $note->is_pinned;
        $note->togglePin();
        event(new NotePinned($note, ! $wasPinned));

        return $note->fresh();
    }

    /**
     * Get notes for a model with optional filters and pagination.
     *
     * @param  Model  $noteable  The model to get notes for
     * @param  array  $filters  Optional filters (type, visibility, author_id, pinned, search)
     * @param  int  $perPage  Items per page (0 = no pagination)
     */
    public function getForModel(
        Model $noteable,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator|Collection {
        $query = $this->notesQueryFor($noteable)->with('author');

        // Apply type filter
        if (! empty($filters['type'])) {
            $type = $filters['type'] instanceof NoteType
                ? $filters['type']
                : NoteType::from($filters['type']);
            $query->where('type', $type);
        }

        // Apply visibility filter
        if (! empty($filters['visibility'])) {
            $visibility = $filters['visibility'] instanceof NoteVisibility
                ? $filters['visibility']
                : NoteVisibility::from($filters['visibility']);
            $query->where('visibility', $visibility);
        }

        // Apply author filter
        if (! empty($filters['author_id'])) {
            $query->where('created_by', (int) $filters['author_id']);
        }

        // Apply pinned filter
        if (isset($filters['pinned'])) {
            $query->where('is_pinned', (bool) $filters['pinned']);
        }

        // Apply search
        if (! empty($filters['search'])) {
            $query->where('content', 'ilike', sprintf('%%%s%%', $filters['search']));
        }

        // Return paginated or all
        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get all notes for a model without pagination.
     */
    public function getAllForModel(Model $noteable): Collection
    {
        return $this->notesQueryFor($noteable)->with('author')->get();
    }

    /**
     * Search notes across all models.
     *
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results
     */
    public function search(string $query, int $limit = 50): Collection
    {
        return $this->applyCurrentUserVisibilityScope(
            Note::with(['author', 'noteable'])
        )
            ->where('content', 'ilike', sprintf('%%%s%%', $query))->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent notes across all models.
     */
    public function getRecent(int $limit = 10): Collection
    {
        return $this->applyCurrentUserVisibilityScope(
            Note::with(['author', 'noteable'])
        )->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get notes by current user.
     */
    public function getMyNotes(int $perPage = 15): LengthAwarePaginator
    {
        return Note::with(['author', 'noteable'])
            ->byAuthor(auth()->id())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function notesQueryFor(Model $noteable): Builder
    {
        return $this->applyCurrentUserVisibilityScope(
            Note::query()
                ->where('noteable_type', $noteable::class)
                ->where('noteable_id', $noteable->getKey())
                ->defaultOrder()
        );
    }

    private function applyCurrentUserVisibilityScope(Builder $query): Builder
    {
        $userId = auth()->id();

        return $query->where(function (Builder $visibilityQuery) use ($userId): void {
            $visibilityQuery->whereIn('visibility', [
                NoteVisibility::Team,
                NoteVisibility::Customer,
            ]);

            if ($userId !== null) {
                $visibilityQuery->orWhere(function (Builder $privateQuery) use ($userId): void {
                    $privateQuery
                        ->where('visibility', NoteVisibility::Private)
                        ->where('created_by', $userId);
                });
            }
        });
    }
}

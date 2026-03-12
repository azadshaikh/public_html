<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\NoteType;
use App\Enums\NoteVisibility;
use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RuntimeException;

/**
 * HasNotes - Add notes functionality to any model
 *
 * This trait provides polymorphic notes relationship and helper methods.
 * Models using this trait can have notes attached to them.
 *
 * Required: notes table with noteable_type and noteable_id columns
 *
 * @example
 * class Website extends Model
 * {
 *     use HasNotes;
 * }
 *
 * // Usage:
 * $website->addNote('Customer prefers email contact');
 * $website->notes; // Get all notes
 * $website->pinnedNotes; // Get pinned notes only
 */
trait HasNotes
{
    /**
     * Get all notes for this model.
     * Ordered by pinned first, then by created_at desc.
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');
    }

    /**
     * Get pinned notes only.
     */
    public function pinnedNotes(): MorphMany
    {
        return $this->notes()->where('is_pinned', true);
    }

    /**
     * Get unpinned notes only.
     */
    public function unpinnedNotes(): MorphMany
    {
        return $this->notes()->where('is_pinned', false);
    }

    /**
     * Get notes filtered by type.
     */
    public function notesByType(NoteType $type): MorphMany
    {
        return $this->notes()->where('type', $type);
    }

    /**
     * Get internal notes only (private + team visibility).
     */
    public function internalNotes(): MorphMany
    {
        return $this->notes()->whereIn('visibility', [
            NoteVisibility::Private->value,
            NoteVisibility::Team->value,
        ]);
    }

    /**
     * Get customer-visible notes only.
     */
    public function customerNotes(): MorphMany
    {
        return $this->notes()->where('visibility', NoteVisibility::Customer->value);
    }

    /**
     * Add a note to this model.
     *
     * @param  string  $content  The note content (HTML allowed)
     * @param  NoteType  $type  The note type (default: Note)
     * @param  NoteVisibility  $visibility  Who can see it (default: Team)
     * @param  array  $metadata  Optional metadata array
     */
    public function addNote(
        string $content,
        NoteType $type = NoteType::Note,
        NoteVisibility $visibility = NoteVisibility::Team,
        array $metadata = []
    ): Note {
        $note = $this->notes()->create([
            'content' => $content,
            'type' => $type,
            'visibility' => $visibility,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        throw_unless($note instanceof Note, RuntimeException::class, 'Unable to create note.');

        return $note;
    }

    /**
     * Add a system-generated note.
     * These are auto-created by the system, not by users.
     *
     * @param  string  $content  The note content
     * @param  array  $metadata  Optional metadata (e.g., backup size, duration)
     */
    public function addSystemNote(string $content, array $metadata = []): Note
    {
        return $this->addNote(
            $content,
            NoteType::System,
            NoteVisibility::Team,
            $metadata
        );
    }

    /**
     * Add a private note (only author can see).
     */
    public function addPrivateNote(string $content, array $metadata = []): Note
    {
        return $this->addNote(
            $content,
            NoteType::Note,
            NoteVisibility::Private,
            $metadata
        );
    }

    /**
     * Add a customer-visible note.
     */
    public function addCustomerNote(string $content, array $metadata = []): Note
    {
        return $this->addNote(
            $content,
            NoteType::Note,
            NoteVisibility::Customer,
            $metadata
        );
    }

    /**
     * Check if this model has any notes.
     */
    public function hasNotes(): bool
    {
        return $this->notes()->exists();
    }

    /**
     * Get the count of notes for this model.
     */
    public function notesCount(): int
    {
        return $this->notes()->count();
    }

    /**
     * Get the count of pinned notes.
     */
    public function pinnedNotesCount(): int
    {
        return $this->pinnedNotes()->count();
    }

    /**
     * Delete all notes when model is force deleted.
     * Call this in the model's boot method if needed.
     */
    public function deleteAllNotes(): void
    {
        $this->notes()->forceDelete();
    }

    /**
     * Get the latest note for this model.
     */
    public function latestNote(): ?Note
    {
        $note = $this->notes()->latest()->first();

        return $note instanceof Note ? $note : null;
    }

    /**
     * Get notes by a specific author.
     */
    public function notesByAuthor(int $userId): MorphMany
    {
        return $this->notes()->where('created_by', $userId);
    }
}

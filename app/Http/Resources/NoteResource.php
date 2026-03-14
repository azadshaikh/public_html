<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NoteType;
use App\Enums\NoteVisibility;
use App\Models\Note;
use App\Traits\DateTimeFormattingTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class NoteResource extends JsonResource
{
    use DateTimeFormattingTrait;

    public function toArray(Request $request): array
    {
        $note = $this->note();
        $type = $note->type instanceof NoteType
            ? $note->type
            : NoteType::tryFrom((string) $note->type) ?? NoteType::Note;
        $visibility = $note->visibility instanceof NoteVisibility
            ? $note->visibility
            : NoteVisibility::tryFrom((string) $note->visibility) ?? NoteVisibility::Team;

        return $this->formatDateTimeFields([
            'id' => (int) $note->getKey(),
            'content' => (string) $note->content,
            'excerpt' => $note->excerpt,
            'is_pinned' => (bool) $note->is_pinned,
            'is_system' => $note->isSystem(),
            'is_private' => $note->isPrivate(),
            'is_editable' => $note->created_by === auth()->id() || (bool) auth()->user()?->can('edit_any_notes'),
            'is_deletable' => $note->is_deletable,
            'type' => [
                'value' => $type->value,
                'label' => $type->label(),
                'badge' => $this->typeBadge($type),
            ],
            'visibility' => [
                'value' => $visibility->value,
                'label' => $visibility->label(),
                'description' => $visibility->description(),
                'badge' => $this->visibilityBadge($visibility),
            ],
            'author' => $note->author
                ? [
                    'id' => (int) $note->author->getKey(),
                    'name' => (string) ($note->author->name ?? 'Unknown user'),
                    'avatar_url' => $note->author->avatar_image,
                ]
                : null,
            'actions' => [
                'update' => route('app.notes.update', $note),
                'destroy' => route('app.notes.destroy', $note),
                'toggle_pin' => route('app.notes.toggle-pin', $note),
            ],
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
            'pinned_at' => $note->pinned_at,
        ], datetimeFields: ['created_at', 'updated_at', 'pinned_at']);
    }

    private function note(): Note
    {
        throw_unless($this->resource instanceof Note, RuntimeException::class, 'NoteResource expects a Note model instance.');

        return $this->resource;
    }

    private function typeBadge(NoteType $type): string
    {
        return match ($type) {
            NoteType::Note => 'outline',
            NoteType::System => 'secondary',
        };
    }

    private function visibilityBadge(NoteVisibility $visibility): string
    {
        return match ($visibility) {
            NoteVisibility::Private => 'outline',
            NoteVisibility::Team => 'secondary',
            NoteVisibility::Customer => 'success',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\NoteService;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * NotesController - CRUD for polymorphic notes.
 */
class NotesController extends Controller
{
    use ActivityTrait;

    protected string $activityLogModule = 'Notes';

    protected string $activityEntityAttribute = 'content';

    public function __construct(private readonly NoteService $noteService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'noteable_type' => ['required', 'string'],
            'noteable_id' => ['required', 'integer'],
            'visibility' => ['nullable', 'string', 'in:private,team,customer'],
        ]);

        try {
            $noteable = $this->resolveNoteable(
                (string) $validated['noteable_type'],
                (int) $validated['noteable_id'],
            );

            $note = $this->noteService->create($noteable, [
                'content' => (string) $validated['content'],
                'visibility' => $validated['visibility'] ?? null,
            ]);

            $this->logCreated($note, 'Note created.', [
                'noteable_type' => $validated['noteable_type'],
                'noteable_id' => $validated['noteable_id'],
            ]);

            return $this->successResponse('Note added successfully.');
        } catch (Exception $exception) {
            return $this->errorResponse('Error creating note: '.$exception->getMessage());
        }
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'visibility' => ['nullable', 'string', 'in:private,team,customer'],
        ]);

        try {
            $this->noteService->update($note, [
                'content' => (string) $validated['content'],
                'visibility' => $validated['visibility'] ?? null,
            ]);

            $this->logUpdated($note, 'Note updated.', [
                'noteable_type' => $note->noteable_type,
                'noteable_id' => $note->noteable_id,
            ]);

            return $this->successResponse('Note updated successfully.');
        } catch (Exception $exception) {
            return $this->errorResponse('Error updating note: '.$exception->getMessage());
        }
    }

    public function destroy(Note $note): JsonResponse
    {
        try {
            $this->noteService->delete($note);

            $this->logDeleted($note, 'Note deleted.', [
                'noteable_type' => $note->noteable_type,
                'noteable_id' => $note->noteable_id,
            ]);

            return $this->successResponse('Note deleted successfully.');
        } catch (Exception $exception) {
            return $this->errorResponse('Error deleting note: '.$exception->getMessage());
        }
    }

    public function togglePin(Note $note): JsonResponse
    {
        try {
            $wasPinned = $note->is_pinned;
            $this->noteService->togglePin($note);

            $action = $wasPinned ? 'unpinned' : 'pinned';

            return $this->successResponse(sprintf('Note %s successfully.', $action));
        } catch (Exception $exception) {
            return $this->errorResponse('Error toggling pin: '.$exception->getMessage());
        }
    }

    private function successResponse(string $message, ?string $redirect = null, array $additional = []): JsonResponse
    {
        $response = [
            'status' => 1,
            'type' => 'toast',
            'message' => $message,
            'refresh' => 'refresh',
        ];

        if ($redirect) {
            $response['redirect'] = $redirect;
        }

        return response()->json(array_merge($response, $additional));
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 2,
            'type' => 'toast',
            'message' => $message,
        ]);
    }

    private function resolveNoteable(string $noteableClass, int $noteableId): Model
    {
        $normalizedClass = ltrim($noteableClass, '\\');

        if (
            $normalizedClass === ''
            || ! class_exists($normalizedClass)
            || ! is_subclass_of($normalizedClass, Model::class)
        ) {
            throw ValidationException::withMessages([
                'noteable_type' => 'Invalid noteable type.',
            ]);
        }

        /** @var Model $noteable */
        $noteable = $normalizedClass::query()->findOrFail($noteableId);

        if (! method_exists($noteable, 'notes')) {
            throw ValidationException::withMessages([
                'noteable_type' => 'This model does not support notes.',
            ]);
        }

        return $noteable;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\NoteService;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function store(Request $request): JsonResponse|RedirectResponse
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

            if ($this->isInertiaRequest($request)) {
                return back()->with('success', 'Note added successfully.');
            }

            return $this->successResponse(
                'Note added successfully.',
                additional: $this->notesPayload($noteable, $note->fresh(['author']))
            );
        } catch (Exception $exception) {
            if ($this->isInertiaRequest($request)) {
                return back()->withInput()->with('error', 'Error creating note. Please try again.');
            }

            return $this->errorResponse('Error creating note: '.$exception->getMessage());
        }
    }

    public function update(Request $request, Note $note): JsonResponse|RedirectResponse
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

            if ($this->isInertiaRequest($request)) {
                return back()->with('success', 'Note updated successfully.');
            }

            return $this->successResponse(
                'Note updated successfully.',
                additional: $this->notesPayload($note->noteable, $note->fresh(['author']))
            );
        } catch (Exception $exception) {
            if ($this->isInertiaRequest($request)) {
                return back()->withInput()->with('error', 'Error updating note. Please try again.');
            }

            return $this->errorResponse('Error updating note: '.$exception->getMessage());
        }
    }

    public function destroy(Request $request, Note $note): JsonResponse|RedirectResponse
    {
        try {
            $noteable = $note->noteable;

            $this->noteService->delete($note);

            $this->logDeleted($note, 'Note deleted.', [
                'noteable_type' => $note->noteable_type,
                'noteable_id' => $note->noteable_id,
            ]);

            if ($this->isInertiaRequest($request)) {
                return back()->with('success', 'Note deleted successfully.');
            }

            return $this->successResponse(
                'Note deleted successfully.',
                additional: $this->notesPayload($noteable)
            );
        } catch (Exception $exception) {
            if ($this->isInertiaRequest($request)) {
                return back()->with('error', 'Error deleting note. Please try again.');
            }

            return $this->errorResponse('Error deleting note: '.$exception->getMessage());
        }
    }

    public function togglePin(Request $request, Note $note): JsonResponse|RedirectResponse
    {
        try {
            $wasPinned = $note->is_pinned;
            $this->noteService->togglePin($note);

            $action = $wasPinned ? 'unpinned' : 'pinned';

            if ($this->isInertiaRequest($request)) {
                return back()->with('success', sprintf('Note %s successfully.', $action));
            }

            return $this->successResponse(
                sprintf('Note %s successfully.', $action),
                additional: $this->notesPayload($note->noteable, $note->fresh(['author']))
            );
        } catch (Exception $exception) {
            if ($this->isInertiaRequest($request)) {
                return back()->with('error', 'Error updating note. Please try again.');
            }

            return $this->errorResponse('Error toggling pin: '.$exception->getMessage());
        }
    }

    private function isInertiaRequest(Request $request): bool
    {
        return $request->header('X-Inertia') !== null;
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

    private function notesPayload(Model $noteable, ?Note $note = null): array
    {
        return [
            'notes' => NoteResource::collection($this->noteService->getAllForModel($noteable))->resolve(request()),
            'note' => $note ? (new NoteResource($note))->resolve(request()) : null,
        ];
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

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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * NotesController - CRUD for polymorphic notes
 *
 * Supports both traditional AJAX and Unpoly partial swaps.
 * For Unpoly requests (up-submit), returns HTML partials.
 * For regular AJAX requests, returns JSON.
 */
class NotesController extends Controller
{
    use ActivityTrait;

    protected string $activityLogModule = 'Notes';

    protected string $activityEntityAttribute = 'content';

    public function __construct(private readonly NoteService $noteService) {}

    /**
     * Store a new note.
     */
    public function store(Request $request): JsonResponse|Response
    {
        $isUnpoly = $this->isUnpolyRequest($request);

        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string'],
            'noteable_type' => ['required', 'string'],
            'noteable_id' => ['required', 'integer'],
            'visibility' => ['nullable', 'string', 'in:private,team,customer'],
        ]);

        if ($validator->fails()) {
            if ($isUnpoly) {
                $noteable = $this->tryResolveNoteable($request);
                if ($noteable instanceof Model) {
                    return $this->renderNotesList($noteable, $validator->errors()->first() ?: 'Could not add note.', 'error', 'error');
                }

                return response('')
                    ->header('X-Flash-Message', rawurlencode($validator->errors()->first() ?: 'Could not add note.'))
                    ->header('X-Flash-Type', 'error')
                    ->header('X-Notes-Result', 'error');
            }

            throw new ValidationException($validator);
        }

        try {
            $noteable = $this->resolveNoteable($request);

            // Create note via service
            $note = $this->noteService->create($noteable, [
                'content' => (string) $request->input('content'),
                'visibility' => $request->input('visibility'),
            ]);

            $this->logCreated($note, 'Note created.', [
                'noteable_type' => $request->input('noteable_type'),
                'noteable_id' => $request->input('noteable_id'),
            ]);

            // For Unpoly requests, return the notes list HTML
            if ($isUnpoly) {
                return $this->renderNotesList($noteable, 'Note added successfully.', 'success', 'created');
            }

            return $this->successResponse('Note added successfully.');
        } catch (ValidationException $e) {
            if ($isUnpoly) {
                $noteable = $this->tryResolveNoteable($request);
                if ($noteable instanceof Model) {
                    $firstError = collect($e->errors())->flatten()->first();

                    return $this->renderNotesList($noteable, $firstError ?: 'Could not add note.', 'error', 'error');
                }

                return response('')
                    ->header('X-Flash-Message', rawurlencode('Could not add note.'))
                    ->header('X-Flash-Type', 'error')
                    ->header('X-Notes-Result', 'error');
            }

            throw $e;
        } catch (Exception $e) {
            if ($isUnpoly) {
                $noteable = $this->tryResolveNoteable($request);
                if ($noteable instanceof Model) {
                    return $this->renderNotesList($noteable, 'Error creating note. Please try again.', 'error', 'error');
                }

                return response('')
                    ->header('X-Flash-Message', rawurlencode('Error creating note. Please try again.'))
                    ->header('X-Flash-Type', 'error')
                    ->header('X-Notes-Result', 'error');
            }

            return $this->errorResponse('Error creating note: '.$e->getMessage());
        }
    }

    /**
     * Show the edit form for a note.
     */
    public function edit(Note $note): View
    {
        return view('app.notes.edit', [
            'note' => $note,
        ]);
    }

    /**
     * Update a note.
     */
    public function update(Request $request, Note $note): JsonResponse|Response
    {
        $isUnpoly = $this->isUnpolyRequest($request);

        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string'],
            'visibility' => ['nullable', 'string', 'in:private,team,customer'],
        ]);

        if ($validator->fails()) {
            if ($isUnpoly) {
                return $this->renderNotesList($note->noteable, $validator->errors()->first() ?: 'Could not update note.', 'error', 'error');
            }

            throw new ValidationException($validator);
        }

        try {
            $this->noteService->update($note, [
                'content' => (string) $request->input('content'),
                'visibility' => $request->input('visibility'),
            ]);

            $this->logUpdated($note, 'Note updated.', [
                'noteable_type' => $note->noteable_type,
                'noteable_id' => $note->noteable_id,
            ]);

            // For Unpoly requests, return the notes list HTML
            if ($isUnpoly) {
                return $this->renderNotesList($note->noteable, 'Note updated successfully.', 'success', 'updated');
            }

            return $this->successResponse('Note updated successfully.');
        } catch (Exception $exception) {
            if ($isUnpoly) {
                return $this->renderNotesList($note->noteable, 'Error updating note. Please try again.', 'error', 'error');
            }

            return $this->errorResponse('Error updating note: '.$exception->getMessage());
        }
    }

    /**
     * Delete a note.
     */
    public function destroy(Request $request, Note $note): JsonResponse|Response
    {
        try {
            $noteable = $note->noteable;

            $this->noteService->delete($note);

            $this->logDeleted($note, 'Note deleted.', [
                'noteable_type' => $note->noteable_type,
                'noteable_id' => $note->noteable_id,
            ]);

            // For Unpoly requests, return the notes list HTML
            if ($this->isUnpolyRequest($request)) {
                return $this->renderNotesList($noteable, 'Note deleted successfully.', 'success', 'deleted');
            }

            return $this->successResponse('Note deleted successfully.');
        } catch (Exception $exception) {
            if ($this->isUnpolyRequest($request)) {
                return $this->renderNotesList($note->noteable, 'Error deleting note. Please try again.', 'error', 'error');
            }

            return $this->errorResponse('Error deleting note: '.$exception->getMessage());
        }
    }

    /**
     * Toggle pin status for a note.
     */
    public function togglePin(Request $request, Note $note): JsonResponse|Response
    {
        try {
            $wasPinned = $note->is_pinned;
            $this->noteService->togglePin($note);

            $action = $wasPinned ? 'unpinned' : 'pinned';

            // For Unpoly requests, return the notes list HTML
            if ($this->isUnpolyRequest($request)) {
                return $this->renderNotesList($note->noteable, sprintf('Note %s successfully.', $action), 'success', 'pinned');
            }

            return $this->successResponse(sprintf('Note %s successfully.', $action));
        } catch (Exception $exception) {
            if ($this->isUnpolyRequest($request)) {
                return $this->renderNotesList($note->noteable, 'Error updating note. Please try again.', 'error', 'error');
            }

            return $this->errorResponse('Error toggling pin: '.$exception->getMessage());
        }
    }

    /**
     * Get notes list partial (for Unpoly target swaps).
     */
    public function notesList(Request $request): View
    {
        $request->validate([
            'noteable_type' => ['required', 'string'],
            'noteable_id' => ['required', 'integer'],
        ]);

        $noteable = $this->resolveNoteable($request);

        $notes = $this->noteService->getAllForModel($noteable);

        return view('components.app.notes-list', [
            'notes' => $notes,
            'model' => $noteable,
            'readOnly' => false,
        ]);
    }

    /**
     * Check if request is from Unpoly.
     */
    private function isUnpolyRequest(Request $request): bool
    {
        if ($request->header('X-Up-Target') !== null) {
            return true;
        }

        return $request->header('X-Up-Fail-Target') !== null;
    }

    /**
     * Render the notes list partial for Unpoly swaps.
     */
    private function renderNotesList(Model $noteable, ?string $flashMessage = null, string $flashType = 'success', string $result = 'ok'): Response
    {
        $notes = $this->noteService->getAllForModel($noteable);

        $response = response()->view('components.app.notes-list', [
            'notes' => $notes,
            'model' => $noteable,
            'readOnly' => false,
        ]);

        $response->header('X-Notes-Result', $result);

        if ($flashMessage) {
            $response->header('X-Flash-Message', rawurlencode($flashMessage));
            $response->header('X-Flash-Type', $flashType);
        }

        return $response;
    }

    private function tryResolveNoteable(Request $request): ?Model
    {
        try {
            return $this->resolveNoteable($request);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Success JSON response.
     */
    private function successResponse(string $message, ?string $redirect = null, array $additional = []): JsonResponse
    {
        $response = [
            'status' => 1,
            'type' => 'toast',
            'message' => $message,
            'refresh' => 'refresh', // Trigger Unpoly refresh
        ];

        if ($redirect) {
            $response['redirect'] = $redirect;
        }

        return response()->json(array_merge($response, $additional));
    }

    /**
     * Error JSON response.
     */
    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 2,
            'type' => 'toast',
            'message' => $message,
        ]);
    }

    private function resolveNoteable(Request $request): Model
    {
        $noteableClass = ltrim((string) $request->input('noteable_type'), '\\');

        if ($noteableClass === '' || ! class_exists($noteableClass) || ! is_subclass_of($noteableClass, Model::class)) {
            throw ValidationException::withMessages([
                'noteable_type' => 'Invalid noteable type.',
            ]);
        }

        /** @var Model $noteable */
        $noteable = $noteableClass::query()->findOrFail((int) $request->input('noteable_id'));

        if (! method_exists($noteable, 'notes')) {
            throw ValidationException::withMessages([
                'noteable_type' => 'This model does not support notes.',
            ]);
        }

        return $noteable;
    }
}

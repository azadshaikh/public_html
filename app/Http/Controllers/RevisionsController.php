<?php

namespace App\Http\Controllers;

use App\Services\RevisionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RevisionsController extends Controller
{
    public function __construct(
        private readonly RevisionService $revisionService
    ) {}

    /**
     * Get revision data for display in modal (client-rendered)
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'revision_id' => ['required', 'integer', 'exists:revisions,id'],
            ]);

            $result = $this->revisionService->getRevisionDataForDisplay($request->integer('revision_id'));

            if (! $result['success']) {
                return $this->errorResponse($result['message']);
            }

            $fields = collect($result['data'])
                ->map(fn (array $field): array => $this->normalizeRevisionField($field))
                ->values()
                ->all();

            return response()->json([
                'status' => 'success',
                'revision' => [
                    'id' => $result['revision']->id,
                    'created_at' => $result['revision']->created_at?->toIso8601String(),
                    'user' => $result['revision']->relationLoaded('user')
                        ? $result['revision']->user?->name
                        : null,
                ],
                'fields' => $fields,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid revision ID: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('Failed to load revision', [
                'revision_id' => $request->input('revision_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to load revision: '.$e->getMessage());
        }
    }

    /**
     * Restore a revision
     */
    public function restore(int $revisionId): JsonResponse
    {
        $result = $this->revisionService->restore($revisionId);

        if (! $result['success']) {
            return $this->errorResponse($result['message']);
        }

        Artisan::call('astero:recache');

        return $this->successResponse(
            $result['message'] ?? 'Revision restored successfully.',
            [
                'model_type' => class_basename($result['model']),
                'model_id' => $result['model']->id,
            ]
        );
    }

    /**
     * Normalize revision field payload for client-side diff.
     */
    private function normalizeRevisionField(array $field): array
    {
        $fieldKey = (string) ($field['field'] ?? '');
        $label = (string) ($field['field_label'] ?? $fieldKey);

        $old = $this->stringifyValue($field['old_value'] ?? '');
        $new = $this->stringifyValue($field['new_value'] ?? '');

        // Prefer readable diffs for JSON blobs
        $language = 'plaintext';
        if ($this->looksLikeJson($old) || $this->looksLikeJson($new)) {
            $oldPretty = $this->tryPrettyJson($old);
            $newPretty = $this->tryPrettyJson($new);

            if ($oldPretty !== null) {
                $old = $oldPretty;
                $language = 'json';
            }

            if ($newPretty !== null) {
                $new = $newPretty;
                $language = 'json';
            }
        }

        // A small hint for HTML-ish content
        if ($language === 'plaintext' && (Str::contains($fieldKey, ['html', 'template', 'content']) || $this->looksLikeHtml($old) || $this->looksLikeHtml($new))) {
            $language = 'html';
        }

        if ($language === 'html') {
            $old = $this->formatHtmlForDiff($old);
            $new = $this->formatHtmlForDiff($new);
        }

        return [
            'field' => $fieldKey,
            'label' => $label,
            'old' => $old,
            'new' => $new,
            'language' => $language,
        ];
    }

    private function formatHtmlForDiff(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        // Insert line breaks between tags to avoid a single giant line.
        $html = preg_replace('/>\s+</', ">\n<", $html) ?? $html;

        // Collapse excessive blank lines.
        return preg_replace("/\n{3,}/", "\n\n", $html) ?? $html;
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function looksLikeJson(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        return (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
            || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'));
    }

    private function tryPrettyJson(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function looksLikeHtml(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        return str_contains($trimmed, '<') && str_contains($trimmed, '>') && preg_match('/<\/?[a-z][\s\S]*>/i', $trimmed) === 1;
    }

    /**
     * Success response helper
     */
    private function successResponse(string $message, array $additionalData = []): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            ...$additionalData,
        ]);
    }

    /**
     * Error response helper
     */
    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 422);
    }
}

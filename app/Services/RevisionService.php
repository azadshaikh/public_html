<?php

namespace App\Services;

use App\Models\Revision;
use App\Models\RevisionData;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevisionService
{
    /**
     * Get revision by ID with relations
     */
    public function getRevision(int $revisionId): ?Revision
    {
        return Revision::with(['revisionData', 'user', 'revisionable'])
            ->find($revisionId);
    }

    /**
     * Get all revisions for a model
     */
    public function getModelRevisions(Model $model, int $limit = 100): Collection
    {
        return $this->revisionQueryForModel($model)
            ->with(['revisionData', 'user'])
            ->limit($limit)
            ->get();
    }

    /**
     * Restore a revision
     */
    public function restore(int $revisionId): array
    {
        DB::beginTransaction();

        try {
            $revision = $this->getRevision($revisionId);

            throw_unless($revision, Exception::class, 'Revision not found.');

            $model = $revision->revisionable;

            throw_unless($model, Exception::class, 'Model not found.');

            $revisionData = $revision->revisionData;

            throw_if($revisionData->isEmpty(), Exception::class, 'No revision data found.');

            $dataToRestore = [];

            $fieldKeys = $revisionData->pluck('field_key')->filter()->unique()->values()->all();
            $previousValues = $this->getPreviousValuesForFields(
                $revision->revisionable_type,
                $revision->revisionable_id,
                $revision->id,
                $fieldKeys
            );

            foreach ($revisionData as $data) {
                if (! $data instanceof RevisionData) {
                    continue;
                }

                $field = $data->field_key;
                // Only restore if the field exists on the model
                if (property_exists($model, $field) || $model->isFillable($field)) {
                    // Restore to the value BEFORE this revision:
                    // - Prefer previous revision's new_value
                    // - If this is the first revision for this field, fall back to stored old_value
                    $dataToRestore[$field] = array_key_exists($field, $previousValues) ? $previousValues[$field] : $data->old_value;
                }
            }

            throw_if($dataToRestore === [], Exception::class, 'No valid fields to restore.');

            // Add updated_by if the model supports it
            if ($model->isFillable('updated_by')) {
                $dataToRestore['updated_by'] = Auth::id();
            }

            $model->update($dataToRestore);

            // Log activity
            activity('Revision')
                ->performedOn($model)
                ->causedBy(Auth::user())
                ->event('restore')
                ->withProperties([
                    'revision_id' => $revisionId,
                    'restored_fields' => array_keys($dataToRestore),
                ])
                ->log('Revision data restored.');

            DB::commit();

            return [
                'success' => true,
                'message' => 'Revision restored to previous values successfully.',
                'model' => $model,
            ];
        } catch (Exception $exception) {
            DB::rollBack();

            Log::error('Failed to restore revision', [
                'revision_id' => $revisionId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Get revision data formatted for display
     */
    public function getRevisionDataForDisplay(int $revisionId): array
    {
        $revision = $this->getRevision($revisionId);

        if (! $revision instanceof Revision) {
            return [
                'success' => false,
                'message' => 'Revision not found.',
            ];
        }

        $model = $revision->revisionable;
        $revisionData = $revision->revisionData;

        $previousValues = $this->getPreviousValuesForFields(
            $revision->revisionable_type,
            $revision->revisionable_id,
            $revision->id,
            $revisionData->pluck('field_key')->filter()->unique()->values()->all()
        );

        $formattedData = [];

        foreach ($revisionData as $data) {
            if (! $data instanceof RevisionData) {
                continue;
            }

            $key = $data->field_key;
            $formattedData[] = [
                'field' => $key,
                'field_label' => $this->formatFieldLabel($key),
                // Prefer previous revision's new_value; fall back to stored baseline old_value.
                'old_value' => array_key_exists($key, $previousValues)
                    ? $previousValues[$key]
                    : ($data->old_value ?? ''),
                'new_value' => $data->new_value,
            ];
        }

        return [
            'success' => true,
            'revision' => $revision,
            'model' => $model,
            'data' => $formattedData,
        ];
    }

    /**
     * Delete old revisions for a model (cleanup)
     */
    public function cleanupOldRevisions(Model $model, int $keepLimit = 50): int
    {
        $revisionQuery = $this->revisionQueryForModel($model);
        $totalRevisions = $revisionQuery->count();

        if ($totalRevisions <= $keepLimit) {
            return 0;
        }

        $revisionsToDelete = $revisionQuery
            ->orderBy('id', 'asc')
            ->limit($totalRevisions - $keepLimit)
            ->get();

        $deletedCount = 0;

        foreach ($revisionsToDelete as $revision) {
            if (! $revision instanceof Revision) {
                continue;
            }

            $revision->revisionData()->delete();
            $revision->delete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    /**
     * Format field key to readable label
     */
    private function formatFieldLabel(string $fieldKey): string
    {
        // Remove common prefixes
        $label = preg_replace('/^(post_|meta_|field_)/', '', $fieldKey);

        // Convert underscores to spaces and title case
        return ucwords(str_replace('_', ' ', $label));
    }

    /**
     * Build a map of field_key => previous new_value for the same model.
     */
    private function getPreviousValuesForFields(string $revisionableType, int|string $revisionableId, int $beforeRevisionId, array $fieldKeys): array
    {
        if ($fieldKeys === []) {
            return [];
        }

        $rows = DB::table('revisions_data as rd')
            ->join('revisions as r', 'r.id', '=', 'rd.revision_id')
            ->where('r.revisionable_type', $revisionableType)
            ->where('r.revisionable_id', $revisionableId)
            ->where('r.id', '<', $beforeRevisionId)
            ->whereIn('rd.field_key', $fieldKeys)
            ->orderByDesc('r.id')
            ->get(['rd.field_key', 'rd.new_value']);

        $previous = [];
        foreach ($rows as $row) {
            $key = $row->field_key;
            if (! array_key_exists($key, $previous)) {
                $previous[$key] = $row->new_value;
            }
        }

        return $previous;
    }

    private function revisionQueryForModel(Model $model): Builder
    {
        return Revision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', (string) $model->getKey());
    }
}

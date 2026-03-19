<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers\Concerns;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait BuildsCmsRevisionPayload
{
    /**
     * @return array{revisions_count:int,revisions:array<int,array<string,mixed>>}
     */
    protected function buildCmsRevisionPayload(Model $model): array
    {
        $revisions = $model->revisionHistory()
            ->with(['user:id,name', 'revisionData'])
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'revisions_count' => $model->revisionHistory()->count(),
            'revisions' => $revisions
                ->map(fn (Revision $revision): array => [
                    'id' => $revision->getKey(),
                    'created_at_formatted' => app_date_time_format($revision->created_at, 'datetime'),
                    'created_at_human' => $revision->created_at?->diffForHumans(),
                    'author_name' => $revision->user?->name,
                    'changes' => $revision->revisionData
                        ->map(fn ($change): array => [
                            'field' => Str::headline((string) $change->getAttribute('field_key')),
                            'old_value' => $this->normalizeRevisionValue($change->oldValue()),
                            'new_value' => $this->normalizeRevisionValue($change->newValue()),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function normalizeRevisionValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        if ($normalized === null || $normalized === '') {
            return null;
        }

        return Str::limit($normalized, 240);
    }
}

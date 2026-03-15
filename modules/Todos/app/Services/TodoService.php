<?php

declare(strict_types=1);

namespace Modules\Todos\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use JsonException;
use Modules\Todos\Definitions\TodoDefinition;
use Modules\Todos\Http\Resources\TodoResource;
use Modules\Todos\Models\Todo;

class TodoService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new TodoDefinition;
    }

    // Form select options
    public function getStatusOptions(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'in_progress', 'label' => 'In Progress'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'on_hold', 'label' => 'On Hold'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }

    public function getPriorityOptions(): array
    {
        return [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'high', 'label' => 'High'],
            ['value' => 'critical', 'label' => 'Critical'],
        ];
    }

    public function getVisibilityOptions(): array
    {
        return [
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'public', 'label' => 'Public'],
        ];
    }

    public function getAssigneeOptions(): array
    {
        return User::visibleToCurrentUser()
            ->select('id', 'email', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn ($user): array => [
                'value' => (string) $user->id,
                'label' => trim((string) $user->name) ?: $user->email,
            ])
            ->values()
            ->prepend(['value' => '', 'label' => 'Unassigned'])
            ->values()
            ->toArray();
    }

    // ================================================================
    // PAGINATED DATA (for index page)
    // ================================================================

    public function getPaginatedTodos(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);

        $paginatedArray = $paginator->toArray();
        $paginatedArray['data'] = TodoResource::collection($paginator->items())->resolve(request());

        return $paginatedArray;
    }

    // ================================================================
    // STATISTICS (for status tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => Todo::query()->whereNull('deleted_at')->count(),
            'pending' => Todo::query()->where('status', 'pending')->whereNull('deleted_at')->count(),
            'in_progress' => Todo::query()->where('status', 'in_progress')->whereNull('deleted_at')->count(),
            'completed' => Todo::query()->where('status', 'completed')->whereNull('deleted_at')->count(),
            'on_hold' => Todo::query()->where('status', 'on_hold')->whereNull('deleted_at')->count(),
            'cancelled' => Todo::query()->where('status', 'cancelled')->whereNull('deleted_at')->count(),
            'trash' => Todo::query()->onlyTrashed()->count(),
        ];
    }

    protected function getResourceClass(): ?string
    {
        return TodoResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'owner:id,first_name,last_name',
            'assignedTo:id,first_name,last_name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $status = $request->route('status', 'all');

        // Handle special status filters
        match ($status) {
            'overdue' => $query->whereNull('completed_at')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString()),
            'starred' => $query->where('is_starred', true),
            'assigned_to_me' => $query->where('assigned_to', Auth::id()),
            default => null,
        };
    }

    protected function prepareCreateData(array $data): array
    {
        $payload = [
            'user_id' => (int) ($data['user_id'] ?? Auth::id()),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'is_starred' => filter_var($data['is_starred'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'assigned_to' => $this->prepareAssignee($data['assigned_to'] ?? null),
            'labels' => $this->prepareLabels($data['labels'] ?? null),
            'metadata' => $this->prepareMetadata($data['metadata'] ?? null),
        ];

        if ($payload['status'] === 'completed') {
            $payload['completed_at'] = now();
        }

        return $payload;
    }

    protected function prepareUpdateData(array $data): array
    {
        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'is_starred' => filter_var($data['is_starred'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'assigned_to' => $this->prepareAssignee($data['assigned_to'] ?? null),
            'labels' => $this->prepareLabels($data['labels'] ?? null),
            'metadata' => $this->prepareMetadata($data['metadata'] ?? null),
        ];

        $payload['completed_at'] = $payload['status'] === 'completed' ? $data['completed_at'] ?? now() : null;

        return $payload;
    }

    // Helper methods
    private function prepareLabels(null|array|string $labels): ?string
    {
        if (is_array($labels)) {
            $labels = array_filter(array_map(trim(...), $labels));

            return $labels === [] ? null : implode(',', $labels);
        }

        if (is_string($labels)) {
            $labelsArray = array_filter(array_map(trim(...), explode(',', $labels)));

            return $labelsArray === [] ? null : implode(',', $labelsArray);
        }

        return null;
    }

    private function prepareAssignee(?string $value): ?int
    {
        if (in_array($value, [null, '', '0'], true)) {
            return null;
        }

        return (int) $value;
    }

    private function prepareMetadata(null|array|string $metadata): ?array
    {
        if (is_array($metadata)) {
            return Arr::where($metadata, fn ($value, $key): bool => $key !== '' && $value !== null);
        }

        if (is_string($metadata) && $metadata !== '') {
            try {
                $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded)
                    ? Arr::where($decoded, fn ($value, $key): bool => $key !== '' && $value !== null)
                    : null;
            } catch (JsonException) {
                return null;
            }
        }

        return null;
    }
}

<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Response;
use Modules\Platform\Definitions\SecretDefinition;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\SecretService;

class SecretController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly SecretService $secretService
    ) {}

    public static function middleware(): array
    {
        return (new SecretDefinition)->getMiddleware();
    }

    public function show(int|string $id): Response
    {
        return parent::show($id);
    }

    protected function service(): SecretService
    {
        return $this->secretService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/secrets';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Secret $secret */
        $secret = $model;

        return [
            'initialValues' => [
                'secretable_type' => (string) ($secret->secretable_type ?? ''),
                'secretable_id' => $secret->secretable_id ? (string) $secret->secretable_id : '',
                'key' => (string) ($secret->key ?? ''),
                'username' => (string) ($secret->username ?? ''),
                'type' => (string) ($secret->type ?? ''),
                'value' => '',
                'is_active' => $secret->exists ? (bool) $secret->is_active : true,
                'expires_at' => $secret->expires_at?->format('Y-m-d\TH:i') ?? '',
            ],
            'typeOptions' => $this->secretService->getTypeOptions(),
            'secretableTypeOptions' => $this->secretService->getSecretableTypeOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Secret $secret */
        $secret = $model;

        return [
            'id' => $secret->getKey(),
            'key' => $secret->key,
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Secret $secret */
        $secret = $model;
        $secret->loadMissing('secretable');

        return [
            'id' => $secret->getKey(),
            'key' => $secret->key,
            'username' => $secret->username,
            'type' => $secret->type,
            'type_label' => $secret->type_label,
            'secretable_type' => $secret->secretable_type,
            'secretable_type_label' => $this->resolveEntityLabel($secret->secretable_type),
            'secretable_id' => $secret->secretable_id,
            'secretable_name' => $this->resolveEntityName($secret->secretable),
            'secretable_href' => $this->resolveEntityHref($secret->secretable),
            'is_active' => (bool) $secret->is_active,
            'is_active_label' => $secret->is_active ? 'Active' : 'Inactive',
            'is_expired' => (bool) $secret->is_expired,
            'expires_at' => app_date_time_format($secret->expires_at, 'datetime'),
            'metadata' => $secret->metadata ?? [],
            'created_at' => app_date_time_format($secret->created_at, 'datetime'),
            'updated_at' => app_date_time_format($secret->updated_at, 'datetime'),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Secret $secret */
        $secret = $model;

        $activities = ActivityLog::query()
            ->forModel(Secret::class, $secret->id)
            ->with('causer')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return [
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
        ];
    }

    private function resolveEntityLabel(?string $class): string
    {
        return match ($class) {
            Domain::class => 'Domain',
            Website::class => 'Website',
            Agency::class => 'Agency',
            Server::class => 'Server',
            Provider::class => 'Provider',
            default => 'Entity',
        };
    }

    private function resolveEntityName(mixed $entity): ?string
    {
        return match (true) {
            $entity instanceof Domain => $entity->name,
            $entity instanceof Website => $entity->name ?? $entity->domain,
            $entity instanceof Agency => $entity->name,
            $entity instanceof Server => $entity->name,
            $entity instanceof Provider => $entity->name,
            default => null,
        };
    }

    private function resolveEntityHref(mixed $entity): ?string
    {
        return match (true) {
            $entity instanceof Domain => route('platform.domains.show', $entity),
            $entity instanceof Website => route('platform.websites.show', $entity),
            $entity instanceof Agency => route('platform.agencies.show', $entity),
            $entity instanceof Server => route('platform.servers.show', $entity),
            $entity instanceof Provider => route('platform.providers.show', $entity),
            default => null,
        };
    }
}

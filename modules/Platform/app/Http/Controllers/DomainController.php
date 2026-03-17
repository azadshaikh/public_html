<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Secret;
use Modules\Platform\Services\DomainService;
use Modules\Platform\Services\WhoisService;

class DomainController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly WhoisService $whoisService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_domains', only: ['index', 'show', 'data']),
            new Middleware('permission:add_domains', only: ['create', 'store', 'lookupDomain', 'getWhoisData']),
            new Middleware('permission:edit_domains', only: ['edit', 'update', 'refreshWhois']),
            new Middleware('permission:delete_domains', only: ['destroy', 'bulkAction', 'forceDelete']),
            new Middleware('permission:restore_domains', only: ['restore']),
        ];
    }

    public function getWhoisData(Request $request): array
    {
        $request->validate([
            'url' => ['required', 'string'],
        ]);

        return $this->whoisService->getWhoisData($request->string('url')->toString(), Domain::class);
    }

    public function lookupDomain(Request $request): JsonResponse
    {
        $request->validate([
            'domain_name' => ['required', 'string', 'max:255'],
        ]);

        $normalizedDomain = Domain::getDomain($request->string('domain_name')->toString());

        $existingDomain = Domain::withTrashed()
            ->where('name', $normalizedDomain)
            ->first();

        if ($existingDomain) {
            $status = $existingDomain->trashed() ? 'trashed' : 'active';

            return response()->json([
                'success' => false,
                'exists' => true,
                'status' => $status,
                'domain_id' => $existingDomain->id,
                'message' => $status === 'trashed'
                    ? 'This domain exists but is in trash. You can restore it instead.'
                    : 'This domain already exists in the system.',
            ]);
        }

        return response()->json([
            'success' => true,
            'exists' => false,
            'domain_name' => $normalizedDomain,
            'whois' => $this->whoisService->getWhoisData($normalizedDomain, Domain::class),
        ]);
    }

    public function refreshWhois(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Domain $domain */
        $domain = $this->findModel((int) $id);

        $result = $this->domainService->refreshWhois($domain);

        if ($result['success'] ?? false) {
            $this->logActivity($domain, ActivityAction::UPDATE, 'WHOIS data refreshed.');

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'] ?? null,
                ]);
            }

            return back()->with('status', $result['message']);
        }

        $message = $result['message'] ?? 'Failed to refresh WHOIS data.';

        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $message], 400);
        }

        return back()->with('error', $message);
    }

    protected function service(): DomainService
    {
        return $this->domainService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/domains';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Domain $domain */
        $domain = $model;
        $registrarId = $domain->exists ? $domain->primaryDomainRegistrar()->value('platform_providers.id') : null;

        return [
            'initialValues' => [
                'name' => (string) ($domain->name ?? ''),
                'type' => (string) ($domain->type ?? ''),
                'agency_id' => $domain->agency_id ? (string) $domain->agency_id : '',
                'status' => (string) ($domain->status ?? 'pending'),
                'registrar_id' => $registrarId ? (string) $registrarId : '',
                'registrar_name' => (string) ($domain->registrar_name ?? ''),
                'registered_date' => $domain->registered_date?->format('Y-m-d') ?? '',
                'expires_date' => $domain->expiry_date?->format('Y-m-d') ?? '',
                'updated_date' => $domain->updated_date?->format('Y-m-d') ?? '',
                'domain_name_server_1' => (string) ($domain->name_server_1 ?? ''),
                'domain_name_server_2' => (string) ($domain->name_server_2 ?? ''),
                'domain_name_server_3' => (string) ($domain->name_server_3 ?? ''),
                'domain_name_server_4' => (string) ($domain->name_server_4 ?? ''),
                'dns_provider' => (string) ($domain->getAttribute('dns_provider') ?? ''),
                'dns_zone_id' => (string) ($domain->dns_zone_id ?? ''),
            ],
            'typeOptions' => $this->domainService->getTypeOptionsForForm(),
            'agencyOptions' => $this->domainService->getAgencyOptions(),
            'registrarOptions' => $this->domainService->getRegistrarOptionsForSelect(),
            'statusOptions' => $this->domainService->getStatusOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Domain $domain */
        $domain = $model;

        return [
            'id' => $domain->getKey(),
            'name' => $domain->domain_name,
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Domain $domain */
        $domain = $model;
        $domain->loadMissing(['agency', 'domainRegistrars', 'dnsRecords', 'secrets']);

        /** @var Secret|null $latestCertificate */
        $latestCertificate = $domain->secrets
            ->filter(fn (Secret $secret): bool => $secret->type === 'ssl_certificate')
            ->sortByDesc(fn (Secret $secret): int => $secret->expires_at?->getTimestamp() ?? 0)
            ->first();

        return [
            'id' => $domain->getKey(),
            'name' => $domain->domain_name,
            'type' => $domain->type,
            'type_label' => $domain->type_label,
            'status' => $domain->status,
            'status_label' => $domain->status_label,
            'agency_name' => $domain->agency?->name,
            'registrar_name' => $domain->domainRegistrars->first()?->name ?? $domain->registrar_name,
            'dns_provider' => (string) ($domain->getAttribute('dns_provider') ?? ''),
            'dns_zone_id' => $domain->dns_zone_id,
            'registered_date' => app_date_time_format($domain->registered_date, 'date'),
            'expires_date' => app_date_time_format($domain->expiry_date, 'date'),
            'updated_date' => app_date_time_format($domain->updated_date, 'date'),
            'name_servers' => array_values(array_filter([
                $domain->name_server_1,
                $domain->name_server_2,
                $domain->name_server_3,
                $domain->name_server_4,
            ], fn (?string $value): bool => filled($value))),
            'dns_records_count' => $domain->dnsRecords->count(),
            'ssl_certificates_count' => $domain->secrets->where('type', 'ssl_certificate')->count(),
            'latest_certificate_expires_at' => $latestCertificate?->expires_at
                ? app_date_time_format($latestCertificate->expires_at, 'date')
                : null,
            'created_at' => app_date_time_format($domain->created_at, 'datetime'),
            'updated_at' => app_date_time_format($domain->updated_at, 'datetime'),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Domain $domain */
        $domain = $model;
        $domain->loadMissing(['dnsRecords', 'secrets']);

        $activities = ActivityLog::query()
            ->forModel(Domain::class, $domain->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $sslCertificates = $domain->secrets
            ->filter(fn (Secret $secret): bool => $secret->type === 'ssl_certificate')
            ->sortByDesc(fn (Secret $secret): int => $secret->expires_at?->getTimestamp() ?? 0)
            ->values();

        return [
            'sslCertificates' => $sslCertificates->map(fn (Secret $certificate): array => [
                'id' => $certificate->getKey(),
                'name' => (string) ($certificate->username ?? $certificate->key),
                'authority' => (string) ($certificate->getMetadata('certificate_authority') ?? 'custom'),
                'expires_at' => app_date_time_format($certificate->expires_at, 'date'),
                'is_expired' => $certificate->is_expired,
                'href' => route('platform.domains.ssl-certificates.show', [$domain, $certificate]),
            ])->all(),
            'dnsRecords' => $domain->dnsRecords->sortBy('name')->map(fn ($record): array => [
                'id' => $record->getKey(),
                'type' => (string) ($record->type ?? '—'),
                'name' => (string) ($record->name ?? '@'),
                'value' => (string) ($record->value ?? '—'),
                'ttl' => $record->ttl,
                'disabled' => (bool) $record->disabled,
            ])->values()->all(),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
        ];
    }
}

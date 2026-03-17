<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\Http\Requests\SslCertificateRequest;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Secret;
use Modules\Platform\Services\DomainSslCertificateService;
use Symfony\Component\HttpFoundation\Response as DownloadResponse;

class DomainSslCertificateController implements HasMiddleware
{
    use ActivityTrait;

    public function __construct(
        private readonly DomainSslCertificateService $sslService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_domains', only: ['index', 'globalIndex', 'show']),
            new Middleware('permission:edit_domains', only: ['create', 'store', 'edit', 'update', 'destroy', 'generateSelfSignedForm', 'generateSelfSigned']),
        ];
    }

    public function globalIndex(Request $request, string $status = 'all'): Response|JsonResponse
    {
        $data = $this->sslService->getGlobalData($request, $status);

        if ($request->expectsJson()) {
            return $this->buildGlobalAjaxResponse($data, $status);
        }

        $certificates = $data['certificates']->through(function (Secret $certificate): array {
            $details = $this->sslService->getCertificateDetails($certificate);
            /** @var Domain|null $domain */
            $domain = $certificate->secretable;
            $authority = (string) ($details['certificate_authority'] ?? 'custom');
            $authorityLabel = match ($authority) {
                DomainSslCertificateService::CA_LETSENCRYPT => "Let's Encrypt",
                DomainSslCertificateService::CA_ZEROSSL => 'ZeroSSL',
                DomainSslCertificateService::CA_GOOGLE => 'Google Trust Services',
                DomainSslCertificateService::CA_SELF_SIGNED => 'Self-Signed',
                default => 'Custom',
            };

            $statusLabel = 'Active';
            if ((bool) ($details['is_expired'] ?? false)) {
                $statusLabel = 'Expired';
            } elseif ((bool) ($details['is_expiring_soon'] ?? false)) {
                $statusLabel = 'Expiring Soon';
            }

            return [
                'id' => $certificate->getKey(),
                'name' => (string) ($details['name'] ?? $certificate->key),
                'domain_name' => $domain?->domain_name,
                'certificate_authority' => $authorityLabel,
                'expires_at' => ! empty($details['expires_at']) ? app_date_time_format($details['expires_at'], 'date') : null,
                'status_label' => $statusLabel,
                'show_url' => $domain ? route('platform.domains.ssl-certificates.show', [$domain, $certificate]) : null,
                'domain_url' => $domain ? route('platform.domains.show', $domain) : null,
                'actions' => $domain ? [
                    'show' => [
                        'key' => 'show',
                        'label' => 'Open certificate',
                        'url' => route('platform.domains.ssl-certificates.show', [$domain, $certificate]),
                    ],
                    'edit' => [
                        'key' => 'edit',
                        'label' => 'Edit certificate',
                        'url' => route('platform.domains.ssl-certificates.edit', [$domain, $certificate]),
                    ],
                ] : [],
            ];
        });

        return Inertia::render('platform/ssl-certificates/index', [
            'config' => [
                'filters' => [],
                'statusTabs' => [
                    ['key' => 'all', 'label' => 'All', 'value' => 'all', 'icon' => 'ri-list-check', 'color' => 'primary'],
                    ['key' => 'active', 'label' => 'Active', 'value' => 'active', 'icon' => 'ri-checkbox-circle-line', 'color' => 'success'],
                    ['key' => 'expiring', 'label' => 'Expiring Soon', 'value' => 'expiring', 'icon' => 'ri-time-line', 'color' => 'warning'],
                    ['key' => 'expired', 'label' => 'Expired', 'value' => 'expired', 'icon' => 'ri-close-circle-line', 'color' => 'danger'],
                ],
                'settings' => [
                    'routePrefix' => 'platform.ssl-certificates',
                    'entityName' => 'SSL certificate',
                    'entityPlural' => 'SSL certificates',
                    'perPage' => 15,
                    'defaultSort' => 'created_at',
                    'defaultDirection' => 'desc',
                ],
            ],
            'rows' => $certificates,
            'filters' => [
                'status' => $status,
                'search' => (string) $request->query('search', ''),
                'sort' => (string) $request->query('sort_column', 'created_at'),
                'direction' => (string) $request->query('sort_direction', 'desc'),
                'per_page' => (int) $request->query('per_page', 15),
            ],
            'statistics' => $data['statistics'],
        ]);
    }

    public function generateSelfSignedForm(Domain $domain): Response
    {
        $defaultName = 'Self-Signed '.ucfirst($domain->domain_name ?? 'Certificate').' '.now()->format('Y');
        $defaultSans = implode("\n", array_filter([
            $domain->domain_name,
            $domain->domain_name ? '*.'.$domain->domain_name : null,
        ]));

        return Inertia::render('platform/ssl-certificates/generate-self-signed', [
            'domain' => [
                'id' => $domain->getKey(),
                'name' => $domain->domain_name,
            ],
            'initialValues' => [
                'name' => $defaultName,
                'key_type' => 'rsa2048',
                'validity_days' => '365',
                'common_name' => (string) ($domain->domain_name ?? ''),
                'country' => 'US',
                'state' => '',
                'city' => '',
                'organization' => '',
                'org_unit' => '',
                'san_domains' => $defaultSans,
            ],
        ]);
    }

    public function generateSelfSigned(Request $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key_type' => ['required', 'in:rsa2048,rsa4096,ec256,ec384'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'common_name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:128'],
            'city' => ['nullable', 'string', 'max:128'],
            'organization' => ['nullable', 'string', 'max:128'],
            'org_unit' => ['nullable', 'string', 'max:128'],
            'san_domains' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->sslService->generateSelfSignedCertificate($domain, $validated);

        $certificate = $this->sslService->create($domain, [
            'name' => $validated['name'],
            'private_key' => $result['private_key'],
            'certificate' => $result['certificate'],
            'certificate_authority' => DomainSslCertificateService::CA_SELF_SIGNED,
        ]);

        $this->logActivity(
            $certificate,
            ActivityAction::CREATE,
            sprintf("Self-signed certificate '%s' generated for domain %s", $validated['name'], $domain->domain_name)
        );

        return to_route('platform.domains.ssl-certificates.show', [$domain, $certificate])
            ->with('status', sprintf("Self-signed certificate '%s' has been generated and saved.", $validated['name']));
    }

    public function create(Domain $domain): Response
    {
        return Inertia::render('platform/ssl-certificates/create', [
            'domain' => [
                'id' => $domain->getKey(),
                'name' => $domain->domain_name,
            ],
            'initialValues' => $this->certificateInitialValues(),
            'certificateAuthorityOptions' => $this->sslService->getCertificateAuthorityOptions(),
        ]);
    }

    public function store(SslCertificateRequest $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validated();

        $certificate = $this->sslService->create($domain, $validated);

        $this->logActivity(
            $certificate,
            ActivityAction::CREATE,
            sprintf("SSL certificate '%s' added for domain %s", $validated['name'], $domain->domain_name)
        );

        return to_route('platform.domains.ssl-certificates.edit', [$domain, $certificate])
            ->with('status', sprintf("SSL certificate '%s' has been added successfully.", $validated['name']));
    }

    public function show(Domain $domain, int $certificate): Response
    {
        $cert = $this->sslService->getCertificate($certificate);

        abort_if(! $cert || $cert->secretable_id !== $domain->id, 404, 'SSL certificate not found.');

        $details = $this->sslService->getCertificateDetails($cert);

        return Inertia::render('platform/ssl-certificates/show', [
            'domain' => [
                'id' => $domain->getKey(),
                'name' => $domain->domain_name,
            ],
            'certificate' => $this->transformCertificate($domain, $cert, $details),
        ]);
    }

    public function edit(Domain $domain, int $certificate): Response
    {
        $cert = $this->sslService->getCertificate($certificate);

        abort_if(! $cert || $cert->secretable_id !== $domain->id, 404, 'SSL certificate not found.');

        $details = $this->sslService->getCertificateDetails($cert);

        return Inertia::render('platform/ssl-certificates/edit', [
            'domain' => [
                'id' => $domain->getKey(),
                'name' => $domain->domain_name,
            ],
            'certificate' => [
                'id' => $cert->getKey(),
                'name' => (string) ($details['name'] ?? $cert->key),
            ],
            'initialValues' => $this->certificateInitialValues($details),
            'certificateDetails' => $this->transformCertificate($domain, $cert, $details),
            'certificateAuthorityOptions' => $this->sslService->getCertificateAuthorityOptions(),
        ]);
    }

    public function update(SslCertificateRequest $request, Domain $domain, int $certificate): RedirectResponse
    {
        $cert = $this->sslService->getCertificate($certificate);

        abort_if(! $cert || $cert->secretable_id !== $domain->id, 404, 'SSL certificate not found.');

        $validated = $request->validated();

        $this->sslService->update($cert, $validated);

        $this->logActivity(
            $cert,
            ActivityAction::UPDATE,
            sprintf("SSL certificate '%s' updated for domain %s", $validated['name'], $domain->domain_name)
        );

        return to_route('platform.domains.ssl-certificates.edit', [$domain, $cert])
            ->with('status', sprintf("SSL certificate '%s' has been updated successfully.", $validated['name']));
    }

    public function destroy(Domain $domain, int $certificate): RedirectResponse
    {
        $cert = $this->sslService->getCertificate($certificate);

        abort_if(! $cert || $cert->secretable_id !== $domain->id, 404, 'SSL certificate not found.');

        $certName = $cert->metadata['name'] ?? $cert->key;

        $this->sslService->delete($cert);

        $this->logActivity(
            $cert,
            ActivityAction::DELETE,
            sprintf("SSL certificate '%s' deleted from domain %s", $certName, $domain->domain_name)
        );

        return to_route('platform.domains.show', $domain)
            ->with('status', sprintf("SSL certificate '%s' has been deleted.", $certName));
    }

    public function downloadPrivateKey(Domain $domain, int $certificate): DownloadResponse
    {
        $cert = $this->sslService->getCertificate($certificate);

        abort_if(! $cert || $cert->secretable_id !== $domain->id, 404, 'SSL certificate not found.');

        $privateKey = $this->sslService->getPrivateKey($cert);
        $certName = $cert->metadata['name'] ?? 'certificate';
        $filename = str_replace([' ', '.'], '_', strtolower($certName)).'_private.key';

        return response($privateKey)
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function downloadCertificate(Domain $domain, int $certificate): DownloadResponse
    {
        $cert = $this->sslService->getCertificate($certificate);

        abort_if(! $cert || $cert->secretable_id !== $domain->id, 404, 'SSL certificate not found.');

        $certificateChain = $this->sslService->getCertificateChain($cert);
        $certName = $cert->metadata['name'] ?? 'certificate';
        $filename = str_replace([' ', '.'], '_', strtolower($certName)).'_fullchain.crt';

        return response($certificateChain)
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    protected function buildGlobalAjaxResponse(array $data, string $status): JsonResponse
    {
        $certificates = $data['certificates'];
        $statistics = $data['statistics'];

        $items = $certificates->getCollection()->map(function (Secret $cert) use ($status): array {
            $details = $this->sslService->getCertificateDetails($cert);
            /** @var Domain|null $domain */
            $domain = $cert->secretable;

            return [
                'id' => $cert->id,
                'name' => $details['name'] ?? $cert->key,
                'domain_name' => $domain?->domain_name,
                'show_url' => $domain ? route('platform.domains.ssl-certificates.show', [$domain, $cert]) : null,
                'domain_url' => $domain ? route('platform.domains.show', $domain) : null,
                'certificate_authority' => $details['certificate_authority'] ?? 'custom',
                'expires_at' => ! empty($details['expires_at']) ? app_date_time_format($details['expires_at'], 'date') : null,
                'status' => $status,
            ];
        });

        return response()->json([
            'items' => $items,
            'statistics' => $statistics,
            'pagination' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ]);
    }

    private function transformCertificate(Domain $domain, Secret $certificate, array $details): array
    {
        return [
            'id' => $certificate->getKey(),
            'name' => (string) ($details['name'] ?? $certificate->key),
            'certificate_authority' => (string) ($details['certificate_authority'] ?? 'custom'),
            'issuer' => $details['issuer'],
            'subject' => $details['subject'],
            'issued_at' => ! empty($details['issued_at']) ? app_date_time_format($details['issued_at'], 'datetime') : null,
            'expires_at' => ! empty($details['expires_at']) ? app_date_time_format($details['expires_at'], 'datetime') : null,
            'serial_number' => $details['serial_number'],
            'fingerprint' => $details['fingerprint'],
            'domains' => is_array($details['domains'] ?? null) ? array_values($details['domains']) : [],
            'is_wildcard' => (bool) ($details['is_wildcard'] ?? false),
            'is_expired' => (bool) ($details['is_expired'] ?? false),
            'is_expiring_soon' => (bool) ($details['is_expiring_soon'] ?? false),
            'days_until_expiry' => $details['days_until_expiry'],
            'download_private_key_url' => route('platform.domains.ssl-certificates.download-key', [$domain, $certificate]),
            'download_certificate_url' => route('platform.domains.ssl-certificates.download-cert', [$domain, $certificate]),
            'created_at' => app_date_time_format($certificate->created_at, 'datetime'),
            'updated_at' => app_date_time_format($certificate->updated_at, 'datetime'),
        ];
    }

    private function certificateInitialValues(array $details = []): array
    {
        return [
            'name' => (string) ($details['name'] ?? ''),
            'certificate_authority' => (string) ($details['certificate_authority'] ?? DomainSslCertificateService::CA_LETSENCRYPT),
            'is_wildcard' => (bool) ($details['is_wildcard'] ?? false),
            'domains' => is_array($details['domains'] ?? null) ? implode(', ', $details['domains']) : '',
            'private_key' => '',
            'certificate' => '',
            'ca_bundle' => '',
            'issuer' => (string) ($details['issuer'] ?? ''),
            'issued_at' => ! empty($details['issued_at']) ? Date::parse($details['issued_at'])->format('Y-m-d') : '',
            'expires_at' => ! empty($details['expires_at']) ? Date::parse($details['expires_at'])->format('Y-m-d') : '',
        ];
    }
}

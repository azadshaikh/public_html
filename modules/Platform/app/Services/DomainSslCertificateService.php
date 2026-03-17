<?php

namespace Modules\Platform\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Secret;
use Throwable;

class DomainSslCertificateService
{
    /**
     * SSL certificate secret type identifier.
     */
    public const SECRET_TYPE = 'ssl_certificate';

    /**
     * Supported Certificate Authorities for acme.sh.
     */
    public const CA_LETSENCRYPT = 'letsencrypt';

    public const CA_ZEROSSL = 'zerossl';

    public const CA_GOOGLE = 'google';

    public const CA_CUSTOM = 'custom';

    public const CA_SELF_SIGNED = 'self_signed';

    /**
     * Generate a self-signed SSL certificate using PHP's OpenSSL extension.
     *
     * @param  array{
     *   name: string,
     *   key_type: string,
     *   validity_days: int,
     *   common_name?: string,
     *   country?: string,
     *   state?: string,
     *   city?: string,
     *   organization?: string,
     *   org_unit?: string,
     *   san_domains?: string,
     * }  $options
     * @return array{private_key: string, certificate: string}
     */
    public function generateSelfSignedCertificate(Domain $domain, array $options): array
    {
        $keyType = $options['key_type'] ?? 'rsa2048';
        $validityDays = max(1, min(3650, (int) ($options['validity_days'] ?? 365)));
        $commonName = $options['common_name'] ?? $domain->domain_name ?? 'localhost';

        // Build OpenSSL config
        $opensslConfig = [
            'digest_alg' => 'sha256',
            'x509_extensions' => 'v3_req',
        ];

        // Resolve key config
        if ($keyType === 'ec256') {
            $opensslConfig['private_key_type'] = OPENSSL_KEYTYPE_EC;
            $opensslConfig['curve_name'] = 'prime256v1';
        } elseif ($keyType === 'ec384') {
            $opensslConfig['private_key_type'] = OPENSSL_KEYTYPE_EC;
            $opensslConfig['curve_name'] = 'secp384r1';
        } else {
            $opensslConfig['private_key_type'] = OPENSSL_KEYTYPE_RSA;
            $opensslConfig['private_key_bits'] = $keyType === 'rsa4096' ? 4096 : 2048;
        }

        // Build DN
        $dn = array_filter([
            'commonName' => $commonName,
            'countryName' => $options['country'] ?? null,
            'stateOrProvinceName' => $options['state'] ?? null,
            'localityName' => $options['city'] ?? null,
            'organizationName' => $options['organization'] ?? null,
            'organizationalUnitName' => $options['org_unit'] ?? null,
        ]);

        // Build SAN list
        $rawSans = $options['san_domains'] ?? '';
        $sanList = array_values(array_unique(array_filter(
            array_map('trim', explode("\n", str_replace(',', "\n", $rawSans)))
        )));

        if (empty($sanList)) {
            $sanList = [$commonName];
        }

        $sanString = implode(', ', array_map(
            fn (string $s): string => 'DNS:'.$s,
            $sanList
        ));

        // Inline OpenSSL config with SANs
        $configContent = "[req]\ndistinguished_name=req_distinguished_name\n[req_distinguished_name]\n[v3_req]\nsubjectAltName={$sanString}\nbasicConstraints=CA:FALSE\nkeyUsage=digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\n";

        $tmpConfigFile = tempnam(sys_get_temp_dir(), 'openssl_cfg_');
        file_put_contents($tmpConfigFile, $configContent);

        try {
            // For key generation, pass config only for RSA (EC key gen ignores it safely,
            // but some PHP builds error if 'config' + 'private_key_type=EC' are combined
            // with a non-default config file).
            $keyGenConfig = $opensslConfig;
            if (in_array($keyType, ['ec256', 'ec384'], true)) {
                unset($keyGenConfig['config']);
            }

            $privateKey = openssl_pkey_new($keyGenConfig);

            if (! $privateKey) {
                throw new \RuntimeException('Failed to generate private key: '.openssl_error_string());
            }

            $opensslConfig['config'] = $tmpConfigFile;

            $csr = openssl_csr_new($dn, $privateKey, $opensslConfig);

            if (! $csr) {
                throw new \RuntimeException('Failed to generate CSR: '.openssl_error_string());
            }

            $x509 = openssl_csr_sign($csr, null, $privateKey, $validityDays, $opensslConfig);

            if (! $x509) {
                throw new \RuntimeException('Failed to sign certificate: '.openssl_error_string());
            }

            openssl_pkey_export($privateKey, $privateKeyPem);
            openssl_x509_export($x509, $certificatePem);
        } finally {
            @unlink($tmpConfigFile);
        }

        return [
            'private_key' => rtrim($privateKeyPem),
            'certificate' => rtrim($certificatePem),
        ];
    }

    /**
     * Get all SSL certificates across all domains.
     */
    public function getAllCertificates(?string $status = 'all'): Collection
    {
        $query = Secret::query()->where('type', self::SECRET_TYPE)
            ->with('secretable');

        return match ($status) {
            'active' => $query->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })->get(),
            'expired' => $query->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->get(),
            'expiring' => $query->where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->where('expires_at', '<', now()->addDays(30))
                ->get(),
            default => $query->get(),
        };
    }

    /**
     * Get all SSL certificates for a domain.
     */
    public function getCertificatesForDomain(Domain $domain): Collection
    {
        return $domain->secrets()
            ->where('type', self::SECRET_TYPE)->latest()
            ->get();
    }

    /**
     * Get a single SSL certificate by ID.
     */
    public function getCertificate(int $id): ?Secret
    {
        return Secret::query()->where('id', $id)
            ->where('type', self::SECRET_TYPE)
            ->first();
    }

    /**
     * Create a new SSL certificate for a domain.
     *
     * @param  array{
     *   name: string,
     *   private_key: string,
     *   certificate: string,
     *   ca_bundle?: string,
     *   is_wildcard?: bool,
     *   domains?: array,
     *   issuer?: string,
     *   issued_at?: string,
     *   expires_at?: string,
     *   certificate_authority?: string
     * }  $data
     */
    public function create(Domain $domain, array $data): Secret
    {
        // Parse certificate for additional info (domains, is_wildcard, expiry, etc.)
        $certInfo = $this->parseCertificate($data['certificate']);

        // Use parsed values from certificate, with user-provided values as fallback
        // Domains and is_wildcard are extracted from the certificate SANs
        $domains = empty($certInfo['domains']) ? $data['domains'] ?? [$domain->domain_name] : $certInfo['domains'];
        $isWildcard = $certInfo['is_wildcard'];

        // Prepare metadata
        $metadata = [
            'name' => $data['name'],
            'is_wildcard' => $isWildcard,
            'domains' => $domains,
            'certificate_authority' => $data['certificate_authority'] ?? self::CA_LETSENCRYPT,
            'issuer' => $data['issuer'] ?? $certInfo['issuer'] ?? null,
            'issued_at' => $data['issued_at'] ?? $certInfo['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? $certInfo['expires_at'] ?? null,
            'subject' => $certInfo['subject'] ?? null,
            'serial_number' => $certInfo['serial_number'] ?? null,
            'fingerprint' => $certInfo['fingerprint'] ?? null,
        ];

        // Store private key in 'value' (encrypted) and certificate/ca_bundle in metadata
        $metadata['certificate'] = $data['certificate'];
        if (! empty($data['ca_bundle'])) {
            $metadata['ca_bundle'] = $data['ca_bundle'];
        }

        // @phpstan-ignore-next-line return.type
        return $domain->secrets()->create([
            'key' => 'domain_ssl_certificate',
            'username' => $data['name'], // Certificate name stored in username field
            'type' => self::SECRET_TYPE,
            'value' => encrypt($data['private_key']), // Private key stored encrypted in value field
            'metadata' => $metadata,
            'is_active' => true,
            'expires_at' => $metadata['expires_at'] ? Date::parse($metadata['expires_at']) : null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update an existing SSL certificate.
     *
     * @param  array{
     *   name?: string,
     *   private_key?: string,
     *   certificate?: string,
     *   ca_bundle?: string,
     *   is_wildcard?: bool,
     *   domains?: array,
     *   certificate_authority?: string,
     *   issuer?: string,
     *   issued_at?: string,
     *   expires_at?: string
     * }  $data
     */
    public function update(Secret $certificate, array $data): Secret
    {
        $updateData = [];
        $metadata = $certificate->metadata ?? [];

        // Update private key if provided (stored encrypted in value field)
        if (isset($data['private_key']) && $data['private_key'] !== '' && $data['private_key'] !== '0') {
            $updateData['value'] = encrypt($data['private_key']);
        }

        // Update certificate if provided (stored in metadata)
        if (isset($data['certificate']) && $data['certificate'] !== '' && $data['certificate'] !== '0') {
            $metadata['certificate'] = $data['certificate'];

            // Re-parse certificate info (extracts domains, is_wildcard, expiry, etc.)
            $certInfo = $this->parseCertificate($data['certificate']);
            $metadata['subject'] = $certInfo['subject'] ?? $metadata['subject'] ?? null;
            $metadata['serial_number'] = $certInfo['serial_number'] ?? $metadata['serial_number'] ?? null;
            $metadata['fingerprint'] = $certInfo['fingerprint'] ?? $metadata['fingerprint'] ?? null;

            // Update domains and is_wildcard from parsed certificate
            if (! empty($certInfo['domains'])) {
                $metadata['domains'] = $certInfo['domains'];
            }

            $metadata['is_wildcard'] = $certInfo['is_wildcard'];

            // Update issued_at from cert if not explicitly provided
            if (! isset($data['issued_at']) && isset($certInfo['issued_at'])) {
                $metadata['issued_at'] = $certInfo['issued_at'];
            }

            // Update expiry from cert if not explicitly provided
            if (! isset($data['expires_at']) && isset($certInfo['expires_at'])) {
                $metadata['expires_at'] = $certInfo['expires_at'];
                $updateData['expires_at'] = Date::parse($certInfo['expires_at']);
            }
        }

        // Update CA bundle if provided (stored in metadata)
        if (isset($data['ca_bundle'])) {
            $metadata['ca_bundle'] = $data['ca_bundle'];
        }

        // Update metadata fields
        if (isset($data['name'])) {
            $metadata['name'] = $data['name'];
            $updateData['username'] = $data['name']; // Update username field with certificate name
        }

        // Only use user-provided values if no certificate was uploaded (parsed values take priority)
        if (isset($data['is_wildcard']) && ! isset($data['certificate'])) {
            $metadata['is_wildcard'] = $data['is_wildcard'];
        }

        if (isset($data['domains']) && ! isset($data['certificate'])) {
            $metadata['domains'] = $data['domains'];
        }

        if (isset($data['certificate_authority'])) {
            $metadata['certificate_authority'] = $data['certificate_authority'];
        }

        if (isset($data['issuer'])) {
            $metadata['issuer'] = $data['issuer'];
        }

        if (isset($data['issued_at'])) {
            $metadata['issued_at'] = $data['issued_at'];
        }

        if (isset($data['expires_at'])) {
            $metadata['expires_at'] = $data['expires_at'];
            $updateData['expires_at'] = Date::parse($data['expires_at']);
        }

        $updateData['metadata'] = $metadata;

        $certificate->update($updateData);

        return $certificate->fresh();
    }

    /**
     * Delete an SSL certificate.
     */
    public function delete(Secret $certificate): bool
    {
        return $certificate->delete();
    }

    /**
     * Restore a deleted SSL certificate.
     */
    public function restore(int $id): ?Secret
    {
        $certificate = Secret::withTrashed()
            ->where('id', $id)
            ->where('type', self::SECRET_TYPE)
            ->first();

        if ($certificate) {
            $certificate->restore();

            return $certificate->fresh();
        }

        return null;
    }

    /**
     * Get certificate details formatted for display.
     */
    public function getCertificateDetails(Secret $certificate): array
    {
        $metadata = $certificate->metadata ?? [];

        return [
            'id' => $certificate->id,
            'name' => $certificate->username ?? $metadata['name'] ?? $certificate->key,
            'is_wildcard' => $metadata['is_wildcard'] ?? false,
            'domains' => $metadata['domains'] ?? [],
            'certificate_authority' => $metadata['certificate_authority'] ?? null,
            'issuer' => $metadata['issuer'] ?? null,
            'subject' => $metadata['subject'] ?? null,
            'issued_at' => $metadata['issued_at'] ?? null,
            'expires_at' => $metadata['expires_at'] ?? null,
            'serial_number' => $metadata['serial_number'] ?? null,
            'fingerprint' => $metadata['fingerprint'] ?? null,
            'is_expired' => $certificate->is_expired,
            'is_expiring_soon' => $this->isExpiringSoon($certificate),
            'days_until_expiry' => $this->getDaysUntilExpiry($certificate),
            'created_at' => $certificate->created_at,
            'updated_at' => $certificate->updated_at,
        ];
    }

    /**
     * Get the decrypted private key.
     */
    public function getPrivateKey(Secret $certificate): ?string
    {
        return $certificate->decrypted_value; // Private key stored encrypted in value field
    }

    /**
     * Get the certificate (from metadata).
     */
    public function getCertificateContent(Secret $certificate): ?string
    {
        return $certificate->metadata['certificate'] ?? null;
    }

    /**
     * Get the CA bundle (from metadata).
     */
    public function getCaBundle(Secret $certificate): ?string
    {
        return $certificate->metadata['ca_bundle'] ?? null;
    }

    /**
     * Get the full certificate chain (certificate + CA bundle).
     */
    public function getCertificateChain(Secret $certificate): ?string
    {
        $cert = $this->getCertificateContent($certificate);
        $caBundle = $this->getCaBundle($certificate);

        if (! $cert) {
            return null;
        }

        if ($caBundle) {
            return $cert."\n".$caBundle;
        }

        return $cert;
    }

    /**
     * Check if a certificate is expiring within the specified days.
     */
    public function isExpiringSoon(Secret $certificate, int $days = 30): bool
    {
        if (! $certificate->expires_at) {
            return false;
        }

        return $certificate->expires_at->isBetween(now(), now()->addDays($days));
    }

    /**
     * Get the number of days until the certificate expires.
     */
    public function getDaysUntilExpiry(Secret $certificate): ?int
    {
        if (! $certificate->expires_at) {
            return null;
        }

        return (int) now()->diffInDays($certificate->expires_at, false);
    }

    /**
     * Validate that the private key matches the certificate.
     */
    public function validateKeyPair(string $privateKey, string $certificate): bool
    {
        try {
            $privKey = openssl_pkey_get_private($privateKey);
            if ($privKey === false) {
                return false;
            }

            $certResource = openssl_x509_read($certificate);
            if ($certResource === false) {
                return false;
            }

            return openssl_x509_check_private_key($certResource, $privKey);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get status options for filtering.
     */
    public function getStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'All Certificates'],
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'expired', 'label' => 'Expired'],
            ['value' => 'expiring', 'label' => 'Expiring Soon'],
        ];
    }

    /**
     * Get global SSL certificate statistics.
     */
    public function getGlobalStatistics(): array
    {
        $allCerts = Secret::query()->where('type', self::SECRET_TYPE)->get();

        $total = $allCerts->count();
        $expired = $allCerts->filter(fn ($c): bool => $c->expires_at && $c->expires_at->isPast())->count();
        $expiring = $allCerts->filter(fn ($c): bool => $c->expires_at && $c->expires_at->isBetween(now(), now()->addDays(30)))->count();
        $active = $total - $expired;

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'expiring' => $expiring,
        ];
    }

    /**
     * Get status navigation for global SSL index.
     */
    public function getStatusNavigation(string $currentStatus): array
    {
        $stats = $this->getGlobalStatistics();
        $baseRoute = 'platform.ssl-certificates.index';

        return [
            [
                'key' => 'all',
                'label' => 'All',
                'icon' => 'ri-shield-keyhole-line',
                'url' => route($baseRoute, ['status' => 'all']),
                'isDefault' => true,
                'count' => $stats['total'],
                'color' => 'primary',
            ],
            [
                'key' => 'active',
                'label' => 'Active',
                'icon' => 'ri-checkbox-circle-line',
                'url' => route($baseRoute, ['status' => 'active']),
                'count' => $stats['active'],
                'color' => 'success',
            ],
            [
                'key' => 'expiring',
                'label' => 'Expiring Soon',
                'icon' => 'ri-alarm-warning-line',
                'url' => route($baseRoute, ['status' => 'expiring']),
                'count' => $stats['expiring'],
                'color' => 'warning',
            ],
            [
                'key' => 'expired',
                'label' => 'Expired',
                'icon' => 'ri-close-circle-line',
                'url' => route($baseRoute, ['status' => 'expired']),
                'count' => $stats['expired'],
                'color' => 'danger',
            ],
        ];
    }

    /**
     * Get available Certificate Authorities for acme.sh.
     */
    public function getCertificateAuthorityOptions(): array
    {
        return [
            ['value' => self::CA_LETSENCRYPT, 'label' => "Let's Encrypt"],
            ['value' => self::CA_ZEROSSL, 'label' => 'ZeroSSL'],
            ['value' => self::CA_GOOGLE, 'label' => 'Google Trust Services'],
            ['value' => self::CA_CUSTOM, 'label' => 'Custom/Manual'],
            ['value' => self::CA_SELF_SIGNED, 'label' => 'Self-Signed'],
        ];
    }

    /**
     * Get the acme.sh server flag for a given CA.
     */
    public function getAcmeServerFlag(string $ca): string
    {
        return match ($ca) {
            self::CA_LETSENCRYPT => '--server letsencrypt',
            self::CA_ZEROSSL => '--server zerossl',
            self::CA_GOOGLE => '--server google',
            default => '',
        };
    }

    /**
     * Get table configuration for DataGrid.
     */
    public function getTableConfig(): array
    {
        return [
            'columns' => [
                [
                    'key' => 'name',
                    'label' => 'Certificate',
                    'template' => 'link',
                    'hrefKey' => 'show_url',
                    'sortable' => true,
                    'searchable' => true,
                    'cellClass' => 'align-start',
                    'cellStyle' => 'min-width:220px;',
                ],
                [
                    'key' => 'domain_name',
                    'label' => 'Domain',
                    'template' => 'link',
                    'hrefKey' => 'domain_url',
                    'sortable' => false,
                    'cellClass' => 'text-start',
                ],
                [
                    'key' => 'certificate_authority',
                    'label' => 'CA',
                    'template' => 'badge',
                    'sortable' => false,
                    'cellClass' => 'text-start',
                ],
                [
                    'key' => 'expires_at',
                    'label' => 'Expires',
                    'template' => 'platform_expiry',
                    'sortable' => true,
                    'cellClass' => 'text-nowrap',
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'template' => 'badge',
                    'sortable' => false,
                    'cellClass' => 'text-start',
                ],
                [
                    'key' => '_actions',
                    'label' => 'Actions',
                    'template' => 'actions',
                    'cellClass' => 'datagrid-action-column',
                    'sortable' => false,
                ],
            ],
            'filters' => [],
            'bulk_actions' => [],
        ];
    }

    /**
     * Get data for global index with pagination.
     */
    public function getGlobalData(Request $request, string $status = 'all'): array
    {
        $query = Secret::query()->where('type', self::SECRET_TYPE)
            ->with('secretable')->latest();

        // Apply status filter
        $query = match ($status) {
            'active' => $query->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            }),
            'expired' => $query->whereNotNull('expires_at')
                ->where('expires_at', '<', now()),
            'expiring' => $query->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->where('expires_at', '<', now()->addDays(30)),
            default => $query,
        };

        // Apply search
        if ($request->filled('search')) {
            $search = $this->escapeLike((string) $request->input('search'));
            $pattern = sprintf('%%%s%%', $search);

            $query->where(function ($q) use ($pattern): void {
                $q->where('key', 'ilike', $pattern)
                    ->orWhere('metadata->name', 'ilike', $pattern);
            });
        }

        // Handle sorting (DataGrid uses sort_column)
        $sortBy = $request->query('sort_column', $request->query('sort_by', 'created_at'));
        $sortDirection = $request->query('sort_direction', 'desc');

        match ($sortBy) {
            'name' => $query->orderBy('username', $sortDirection),
            'expires_at' => $query->orderBy('expires_at', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query->latest(),
        };

        // Paginate
        $perPage = $request->query('per_page', 15);
        $certificates = $query->paginate($perPage);

        return [
            'certificates' => $certificates,
            'statistics' => $this->getGlobalStatistics(),
        ];
    }

    /**
     * Parse X.509 certificate to extract metadata.
     *
     * @return array{
     *   subject: string|null,
     *   issuer: string|null,
     *   issued_at: string|null,
     *   expires_at: string|null,
     *   serial_number: string|null,
     *   fingerprint: string|null,
     *   domains: array,
     *   is_wildcard: bool
     * }
     */
    protected function parseCertificate(string $certificatePem): array
    {
        $result = [
            'subject' => null,
            'issuer' => null,
            'issued_at' => null,
            'expires_at' => null,
            'serial_number' => null,
            'fingerprint' => null,
            'domains' => [],
            'is_wildcard' => false,
        ];

        try {
            $certResource = openssl_x509_read($certificatePem);
            if ($certResource === false) {
                return $result;
            }

            $certData = openssl_x509_parse($certResource);
            if ($certData === false) {
                return $result;
            }

            // Extract subject
            if (isset($certData['subject']['CN'])) {
                $result['subject'] = $certData['subject']['CN'];
            }

            // Extract issuer
            if (isset($certData['issuer']['O'])) {
                $result['issuer'] = $certData['issuer']['O'];
            } elseif (isset($certData['issuer']['CN'])) {
                $result['issuer'] = $certData['issuer']['CN'];
            }

            // Extract dates
            if (isset($certData['validFrom_time_t'])) {
                $result['issued_at'] = date('Y-m-d H:i:s', $certData['validFrom_time_t']);
            }

            if (isset($certData['validTo_time_t'])) {
                $result['expires_at'] = date('Y-m-d H:i:s', $certData['validTo_time_t']);
            }

            // Extract serial number
            if (isset($certData['serialNumberHex'])) {
                $result['serial_number'] = $certData['serialNumberHex'];
            } elseif (isset($certData['serialNumber'])) {
                $result['serial_number'] = (string) $certData['serialNumber'];
            }

            // Calculate fingerprint
            $result['fingerprint'] = openssl_x509_fingerprint($certResource, 'sha256');

            // Extract domains from Subject Alternative Names (SANs)
            $domains = [];

            // Add CN (Common Name) as a domain if present
            if (isset($certData['subject']['CN'])) {
                $domains[] = $certData['subject']['CN'];
            }

            // Extract SANs from extensions
            if (isset($certData['extensions']['subjectAltName'])) {
                $sans = $certData['extensions']['subjectAltName'];
                // SANs are comma-separated: "DNS:example.com, DNS:*.example.com, DNS:www.example.com"
                $sanParts = array_map(trim(...), explode(',', $sans));
                foreach ($sanParts as $san) {
                    // Extract DNS entries (format: "DNS:domain.com")
                    if (str_starts_with($san, 'DNS:')) {
                        $domain = substr($san, 4); // Remove "DNS:" prefix
                        if ($domain !== '' && $domain !== '0' && ! in_array($domain, $domains)) {
                            $domains[] = $domain;
                        }
                    }
                }
            }

            $result['domains'] = $domains;

            // Check if any domain is a wildcard
            $result['is_wildcard'] = collect($domains)->contains(fn ($d): bool => str_starts_with((string) $d, '*.'));
        } catch (Throwable) {
            // Return empty result on any parsing error
        }

        return $result;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}

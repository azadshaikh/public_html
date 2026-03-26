<?php

declare(strict_types=1);

namespace Modules\Platform\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Platform\Models\Website;
use RuntimeException;
use Throwable;

class WebsiteDomainVerificationService
{
    public function ensureVerificationToken(Website $website): string
    {
        $existingToken = $website->getMetadata('domain_verification.token');

        if (is_string($existingToken) && $existingToken !== '') {
            return $existingToken;
        }

        $token = Str::random(40);
        $website->setMetadata('domain_verification.token', $token);
        $website->setMetadata('domain_verification.path', $this->verificationPath());
        $website->save();

        return $token;
    }

    public function verificationPath(): string
    {
        $path = trim((string) config('platform.domain_verification.path', '/.well-known/astero-domain-verification.txt'));

        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new RuntimeException('Platform domain verification path must be an absolute web path.');
        }

        return $path;
    }

    public function remoteVerificationFilePath(Website $website): string
    {
        $username = $website->website_username;
        $domain = $this->rootDomain($website);

        if (! is_string($username) || $username === '') {
            throw new RuntimeException('Website username is required to publish the domain verification file.');
        }

        return sprintf(
            '/home/%s/web/%s/public_html%s',
            $username,
            $domain,
            $this->verificationPath()
        );
    }

    public function publishVerificationFile(Website $website): void
    {
        $server = $website->server;

        if (! $server) {
            throw new RuntimeException('Website server is required to publish the domain verification file.');
        }

        $token = $this->ensureVerificationToken($website);
        $remoteFilePath = $this->remoteVerificationFilePath($website);
        $remoteDirectory = dirname($remoteFilePath);
        $username = (string) $website->website_username;

        $command = sprintf(
            "install -d -m 0755 %s && printf '%%s\\n' %s > %s && chmod 0644 %s && chown %s:%s %s",
            escapeshellarg($remoteDirectory),
            escapeshellarg($token),
            escapeshellarg($remoteFilePath),
            escapeshellarg($remoteFilePath),
            escapeshellarg($username),
            escapeshellarg($username),
            escapeshellarg($remoteFilePath),
        );

        $result = resolve(ServerSSHService::class)->executeCommand($server, $command, 30);

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException('Failed to publish the domain verification file: '.($result['message'] ?? 'Unknown error'));
        }

        $website->setMetadata('domain_verification.path', $this->verificationPath());
        $website->setMetadata('domain_verification.published_at', now()->toIso8601String());
        $website->save();
    }

    /**
     * @return string[]
     */
    public function verificationHosts(Website $website): array
    {
        $rootDomain = $this->rootDomain($website);
        $records = $website->domainRecord?->getMetadata('dns_instructions.records');

        $hosts = collect(is_array($records) ? $records : [])
            ->filter(fn ($record): bool => is_array($record))
            ->filter(function (array $record): bool {
                $type = strtoupper((string) ($record['type'] ?? ''));
                $name = (string) ($record['name'] ?? '');

                return in_array($type, ['A', 'CNAME'], true)
                    && ! str_starts_with($name, '_');
            })
            ->map(fn (array $record): string => $this->normalizeHost((string) ($record['name'] ?? ''), $rootDomain))
            ->filter(fn (string $host): bool => $host !== '')
            ->unique()
            ->values()
            ->all();

        if ($hosts === []) {
            return [$rootDomain, 'www.'.$rootDomain];
        }

        return $hosts;
    }

    /**
     * @return string[]
     */
    public function verificationUrls(Website $website): array
    {
        return collect($this->verificationHosts($website))
            ->map(fn (string $host): string => sprintf('http://%s%s', $host, $this->verificationPath()))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     path: string,
     *     token: string,
     *     urls: string[],
     *     checks: array<int, array{host: string, url: string, matched: bool, status_code: int|null, location: string|null, body: string|null, error: string|null}>,
     *     passes: bool
     * }
     */
    public function verifyWebsite(Website $website): array
    {
        $token = $this->ensureVerificationToken($website);
        $checks = collect($this->verificationHosts($website))
            ->map(fn (string $host): array => $this->verifyHost($host, $token))
            ->values()
            ->all();

        return [
            'path' => $this->verificationPath(),
            'token' => $token,
            'urls' => array_map(fn (array $check): string => $check['url'], $checks),
            'checks' => $checks,
            'passes' => $checks !== [] && collect($checks)->every(fn (array $check): bool => $check['matched']),
        ];
    }

    /**
     * @return array{host: string, url: string, matched: bool, status_code: int|null, location: string|null, body: string|null, error: string|null}
     */
    public function verifyHost(string $host, string $token): array
    {
        $url = sprintf('http://%s%s', $host, $this->verificationPath());

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->httpTimeout())
                ->withOptions(['allow_redirects' => false])
                ->get($url);

            $body = trim($response->body());

            return [
                'host' => $host,
                'url' => $url,
                'matched' => $response->successful() && hash_equals($token, $body),
                'status_code' => $response->status(),
                'location' => $response->header('Location'),
                'body' => $body,
                'error' => null,
            ];
        } catch (ConnectionException|RequestException $exception) {
            return [
                'host' => $host,
                'url' => $url,
                'matched' => false,
                'status_code' => null,
                'location' => null,
                'body' => null,
                'error' => $exception->getMessage(),
            ];
        } catch (Throwable $throwable) {
            return [
                'host' => $host,
                'url' => $url,
                'matched' => false,
                'status_code' => null,
                'location' => null,
                'body' => null,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    private function rootDomain(Website $website): string
    {
        $rootDomain = (string) ($website->domainRecord?->name ?? $website->domain);
        $rootDomain = strtolower(rtrim(trim($rootDomain), '.'));

        if ($rootDomain === '') {
            throw new RuntimeException('Website domain is required for domain verification.');
        }

        return $rootDomain;
    }

    private function normalizeHost(string $name, string $rootDomain): string
    {
        $normalizedName = strtolower(rtrim(trim($name), '.'));

        if ($normalizedName === '' || $normalizedName === '@' || $normalizedName === $rootDomain) {
            return $rootDomain;
        }

        if (str_contains($normalizedName, '.')) {
            return $normalizedName;
        }

        return $normalizedName.'.'.$rootDomain;
    }

    private function httpTimeout(): int
    {
        return max(1, (int) config('platform.domain_verification.http_timeout', 10));
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('platform.domain_verification.connect_timeout', 5));
    }
}

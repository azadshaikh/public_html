<?php

declare(strict_types=1);

namespace Modules\Platform\Libs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Production-grade DNS resolver that queries public DNS servers directly.
 *
 * Uses `dig` to query Cloudflare (1.1.1.1) and Google (8.8.8.8) resolvers
 * instead of relying on the server's local resolver (which may be cached/stale).
 *
 * All verification methods require BOTH resolvers to confirm the expected records
 * to ensure the DNS change has propagated globally.
 */
class DnsResolver
{
    /**
     * Public DNS resolvers to query. Both must agree for verified = true.
     */
    private const RESOLVERS = [
        '1.1.1.1',   // Cloudflare
        '8.8.8.8',   // Google
    ];

    /**
     * Timeout in seconds for each dig query.
     */
    private const QUERY_TIMEOUT = 5;

    /**
     * Number of retries for each dig query.
     */
    private const QUERY_TRIES = 2;

    /**
     * Verify NS delegation matches expected nameservers.
     *
     * Returns true only if ALL resolvers see the expected NS records.
     *
     * @param  string[]  $expectedNameservers  Nameservers to look for (e.g., ['kiki.bunny.net', 'coco.bunny.net'])
     */
    public static function verifyNsDelegation(string $domain, array $expectedNameservers): bool
    {
        $expectedLower = array_map(fn (string $ns) => rtrim(strtolower($ns), '.'), $expectedNameservers);

        foreach (self::RESOLVERS as $resolver) {
            $currentNs = self::queryRecords($domain, 'NS', $resolver);

            if (empty($currentNs)) {
                Log::debug(sprintf('DnsResolver: No NS records from %s for %s', $resolver, $domain));

                return false;
            }

            $missing = array_diff($expectedLower, $currentNs);

            if (! empty($missing)) {
                Log::debug(sprintf(
                    'DnsResolver: NS mismatch from %s for %s — missing: [%s], found: [%s]',
                    $resolver,
                    $domain,
                    implode(', ', $missing),
                    implode(', ', $currentNs)
                ));

                return false;
            }
        }

        return true;
    }

    /**
     * Verify a single DNS record (A, CNAME, TXT) exists with the expected value.
     *
     * Returns true only if ALL resolvers see the expected record.
     */
    public static function verifyRecord(string $name, string $type, string $expectedValue): bool
    {
        $expectedLower = rtrim(strtolower($expectedValue), '.');
        $type = strtoupper($type);

        foreach (self::RESOLVERS as $resolver) {
            $records = self::queryRecords($name, $type, $resolver);

            $found = false;
            foreach ($records as $value) {
                if ($value === $expectedLower) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                Log::debug(sprintf(
                    'DnsResolver: %s record for %s not matched on %s — expected: %s, found: [%s]',
                    $type,
                    $name,
                    $resolver,
                    $expectedLower,
                    implode(', ', $records)
                ));

                return false;
            }
        }

        return true;
    }

    /**
     * Verify a CNAME target, allowing apex flattening when the resolver returns
     * A / AAAA answers that match the expected hostname's resolved addresses.
     *
     * Returns true only if ALL resolvers either see the exact CNAME target or
     * return addresses that intersect with the expected hostname's addresses.
     */
    public static function verifyCnameTarget(string $name, string $expectedValue, bool $allowFlattenedAddress = false): bool
    {
        $expectedLower = rtrim(strtolower($expectedValue), '.');

        foreach (self::RESOLVERS as $resolver) {
            $cnameRecords = self::queryRecords($name, 'CNAME', $resolver);

            if (in_array($expectedLower, $cnameRecords, true)) {
                continue;
            }

            if (! $allowFlattenedAddress) {
                Log::debug(sprintf(
                    'DnsResolver: CNAME record for %s not matched on %s — expected: %s, found: [%s]',
                    $name,
                    $resolver,
                    $expectedLower,
                    implode(', ', $cnameRecords)
                ));

                return false;
            }

            $resolvedAddresses = array_values(array_unique(array_merge(
                self::queryRecords($name, 'A', $resolver),
                self::queryRecords($name, 'AAAA', $resolver),
            )));
            $expectedAddresses = array_values(array_unique(array_merge(
                self::queryRecords($expectedLower, 'A', $resolver),
                self::queryRecords($expectedLower, 'AAAA', $resolver),
            )));

            if ($resolvedAddresses !== [] && $expectedAddresses !== []) {
                $matchingAddresses = array_intersect($resolvedAddresses, $expectedAddresses);

                if ($matchingAddresses !== []) {
                    continue;
                }
            }

            Log::debug(sprintf(
                'DnsResolver: Flattened CNAME target for %s not matched on %s — expected target: %s, resolved: [%s], target resolved: [%s]',
                $name,
                $resolver,
                $expectedLower,
                implode(', ', $resolvedAddresses),
                implode(', ', $expectedAddresses)
            ));

            return false;
        }

        return true;
    }

    /**
     * Query NS records for a domain from all resolvers.
     *
     * Returns an array of nameserver results per resolver for debugging/display.
     *
     * @return array<string, string[]> Keyed by resolver IP
     */
    public static function queryNsFromAllResolvers(string $domain): array
    {
        $results = [];
        foreach (self::RESOLVERS as $resolver) {
            $results[$resolver] = self::queryRecords($domain, 'NS', $resolver);
        }

        return $results;
    }

    /**
     * Query observed NS records for a domain and detect if it is unregistered (NXDOMAIN).
     *
     * Returns the union of NS records seen across all resolvers and whether NXDOMAIN
     * was returned (meaning the domain is not yet registered in the global DNS).
     *
     * @return array{nameservers: string[], not_registered: bool}
     */
    public static function queryNsObserved(string $domain): array
    {
        $observed = [];
        $notRegistered = false;

        foreach (self::RESOLVERS as $resolver) {
            // Query with status line so we can detect NXDOMAIN
            $command = sprintf(
                'dig @%s %s NS +noall +answer +comments +time=%d +tries=%d 2>/dev/null',
                escapeshellarg($resolver),
                escapeshellarg($domain),
                self::QUERY_TIMEOUT,
                self::QUERY_TRIES
            );

            $result = Process::timeout(self::QUERY_TIMEOUT * self::QUERY_TRIES + 5)->run($command);

            if ($result->successful()) {
                $output = $result->output();

                // Detect domain not registered
                if (str_contains($output, 'NXDOMAIN')) {
                    $notRegistered = true;
                }

                // Parse NS records from answer section (lines not starting with ;)
                foreach (explode("\n", $output) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, ';')) {
                        continue;
                    }
                    // Answer lines: name ttl class type value
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 5 && strtoupper($parts[3]) === 'NS') {
                        $ns = rtrim(strtolower($parts[4]), '.');
                        if ($ns !== '') {
                            $observed[] = $ns;
                        }
                    }
                }
            }
        }

        return [
            'nameservers' => array_values(array_unique($observed)),
            'not_registered' => $notRegistered,
        ];
    }

    /**
     * Query DNS records from a specific resolver using dig.
     *
     * @return string[] Array of record values (lowercased, trailing dot stripped)
     */
    public static function queryRecords(string $name, string $type, string $resolver): array
    {
        $type = strtoupper($type);

        // Validate inputs to prevent command injection
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new RuntimeException(sprintf('Invalid DNS name: %s', $name));
        }

        if (! in_array($type, ['A', 'AAAA', 'CNAME', 'NS', 'TXT', 'MX', 'SOA'], true)) {
            throw new RuntimeException(sprintf('Unsupported DNS record type: %s', $type));
        }

        if (! filter_var($resolver, FILTER_VALIDATE_IP)) {
            throw new RuntimeException(sprintf('Invalid resolver IP: %s', $resolver));
        }

        $command = sprintf(
            'dig @%s %s %s +short +time=%d +tries=%d 2>/dev/null',
            escapeshellarg($resolver),
            escapeshellarg($name),
            escapeshellarg($type),
            self::QUERY_TIMEOUT,
            self::QUERY_TRIES
        );

        $result = Process::timeout(self::QUERY_TIMEOUT * self::QUERY_TRIES + 5)
            ->run($command);

        if (! $result->successful()) {
            Log::warning(sprintf('DnsResolver: dig command failed (exit %d) for %s %s @%s', $result->exitCode(), $name, $type, $resolver));

            return [];
        }

        $output = array_filter(explode("\n", $result->output()), fn (string $line) => trim($line) !== '');

        // Parse output: each line is a record value
        // Strip trailing dots, lowercase, filter empty lines
        return array_values(array_map(
            fn (string $line) => rtrim(strtolower(trim($line)), '.'),
            $output
        ));
    }

    /**
     * Check if dig is available on the system.
     */
    public static function isAvailable(): bool
    {
        $result = Process::run('which dig 2>/dev/null');

        return $result->successful() && trim($result->output()) !== '';
    }
}

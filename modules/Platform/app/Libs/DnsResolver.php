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
 * Record verification succeeds as soon as ANY configured public resolver confirms
 * the expected value. This keeps provisioning moving when one resolver is lagging
 * behind the other.
 */
class DnsResolver
{
    /**
     * Public DNS resolvers to query.
     */
    private const RESOLVERS = [
        '1.1.1.1',   // Cloudflare
        '8.8.8.8',   // Google
    ];

    /**
     * Get the public resolvers used by this class.
     *
     * @return string[]
     */
    public static function resolvers(): array
    {
        return self::RESOLVERS;
    }

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
     * Returns true as soon as ANY resolver sees the expected NS records.
     *
     * @param  string[]  $expectedNameservers  Nameservers to look for (e.g., ['kiki.bunny.net', 'coco.bunny.net'])
     */
    public static function verifyNsDelegation(string $domain, array $expectedNameservers): bool
    {
        $expectedLower = array_map(fn (string $ns) => rtrim(strtolower($ns), '.'), $expectedNameservers);
        $resolverMisses = [];

        foreach (self::RESOLVERS as $resolver) {
            $currentNs = self::queryRecords($domain, 'NS', $resolver);

            if (empty($currentNs)) {
                $resolverMisses[] = sprintf('%s: no NS records', $resolver);

                continue;
            }

            $missing = array_diff($expectedLower, $currentNs);

            if ($missing === []) {
                return true;
            }

            $resolverMisses[] = sprintf(
                '%s: missing [%s], found [%s]',
                $resolver,
                implode(', ', $missing),
                implode(', ', $currentNs)
            );
        }

        Log::debug(sprintf(
            'DnsResolver: NS delegation not matched for %s — %s',
            $domain,
            implode('; ', $resolverMisses)
        ));

        return false;
    }

    /**
     * Verify a single DNS record (A, CNAME, TXT) exists with the expected value.
     *
     * Returns true as soon as ANY resolver sees the expected record.
     */
    public static function verifyRecord(string $name, string $type, string $expectedValue): bool
    {
        $expectedLower = rtrim(strtolower($expectedValue), '.');
        $type = strtoupper($type);
        $resolverMisses = [];

        foreach (self::RESOLVERS as $resolver) {
            $records = self::queryRecords($name, $type, $resolver);

            $found = false;
            foreach ($records as $value) {
                if ($value === $expectedLower) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                return true;
            }

            $resolverMisses[] = sprintf(
                '%s: expected %s, found [%s]',
                $resolver,
                $expectedLower,
                implode(', ', $records)
            );
        }

        Log::debug(sprintf(
            'DnsResolver: %s record for %s not matched — %s',
            $type,
            $name,
            implode('; ', $resolverMisses)
        ));

        return false;
    }

    /**
     * Verify a CNAME target, allowing apex flattening when the resolver returns
     * A / AAAA answers that match the expected hostname's resolved addresses.
     *
     * Returns true as soon as ANY resolver either sees the exact CNAME target or
     * returns addresses that intersect with the expected hostname's addresses.
     */
    public static function verifyCnameTarget(string $name, string $expectedValue, bool $allowFlattenedAddress = false): bool
    {
        $expectedLower = rtrim(strtolower($expectedValue), '.');
        $resolverMisses = [];

        foreach (self::RESOLVERS as $resolver) {
            $cnameRecords = self::queryRecords($name, 'CNAME', $resolver);

            if (in_array($expectedLower, $cnameRecords, true)) {
                return true;
            }

            if (! $allowFlattenedAddress) {
                $resolverMisses[] = sprintf(
                    '%s: expected %s, found [%s]',
                    $resolver,
                    $expectedLower,
                    implode(', ', $cnameRecords)
                );

                continue;
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
                    return true;
                }
            }

            $resolverMisses[] = sprintf(
                '%s: expected target %s, cname [%s], resolved [%s], target resolved [%s]',
                $resolver,
                $expectedLower,
                implode(', ', $cnameRecords),
                implode(', ', $resolvedAddresses),
                implode(', ', $expectedAddresses)
            );
        }

        Log::debug(sprintf(
            'DnsResolver: CNAME target for %s not matched — %s',
            $name,
            implode('; ', $resolverMisses)
        ));

        return false;
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
     * Query a record type from all public resolvers.
     *
     * @return array<string, string[]>
     */
    public static function queryRecordsFromAllResolvers(string $name, string $type): array
    {
        $results = [];

        foreach (self::RESOLVERS as $resolver) {
            $results[$resolver] = self::queryRecords($name, $type, $resolver);
        }

        return $results;
    }

    /**
     * Run a trace query for a DNS record and return a compact parsed summary.
     *
     * @return array{records: string[], nameservers: string[], output: string}
     */
    public static function traceRecord(string $name, string $type): array
    {
        $type = strtoupper($type);
        self::assertValidQuery($name, $type);

        $command = sprintf(
            'dig %s %s +trace +time=%d +tries=1 2>/dev/null',
            escapeshellarg($name),
            escapeshellarg($type),
            self::QUERY_TIMEOUT
        );

        $result = Process::timeout(self::QUERY_TIMEOUT + 20)->run($command);

        if (! $result->successful()) {
            Log::warning(sprintf('DnsResolver: dig +trace failed (exit %d) for %s %s', $result->exitCode(), $name, $type));

            return [
                'records' => [],
                'nameservers' => [],
                'output' => '',
            ];
        }

        $records = [];
        $nameservers = [];
        $normalizedName = rtrim(strtolower($name), '.');
        $output = trim($result->output());

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 5) {
                continue;
            }

            $recordName = rtrim(strtolower($parts[0]), '.');
            $recordType = strtoupper($parts[3]);
            $recordValue = rtrim(strtolower((string) end($parts)), '.');

            if ($recordType === 'NS') {
                $nameservers[] = $recordValue;
            }

            if ($recordName === $normalizedName && $recordType === $type) {
                $records[] = $recordValue;
            }
        }

        return [
            'records' => array_values(array_unique($records)),
            'nameservers' => array_values(array_unique($nameservers)),
            'output' => $output,
        ];
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
        self::assertValidQuery($name, $type);

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

    private static function assertValidQuery(string $name, string $type): void
    {
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new RuntimeException(sprintf('Invalid DNS name: %s', $name));
        }

        if (! in_array($type, ['A', 'AAAA', 'CNAME', 'NS', 'TXT', 'MX', 'SOA'], true)) {
            throw new RuntimeException(sprintf('Unsupported DNS record type: %s', $type));
        }
    }
}

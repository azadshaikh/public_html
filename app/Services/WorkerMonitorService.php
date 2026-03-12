<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads queue worker process information via two complementary approaches:
 *
 *  1. `systemctl status supervisor` (via proc_open) — gives us the cgroup
 *     process tree with PIDs and cmdlines. Used for supervisor status and
 *     process discovery. Works even when /proc visibility is restricted.
 *
 *  2. /proc/{pid}/status + /proc/{pid}/stat — gives memory (VmRSS) and
 *     process start time to compute uptime. Used as a supplemental read
 *     once PIDs are known from systemctl.
 *
 * Neither shell_exec, exec, popen, nor system are called (all disabled
 * in HestiaCP's PHP-FPM open_basedir config). proc_open is allowed.
 */
class WorkerMonitorService
{
    // ════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════════

    /**
     * Returns combined supervisor + process stats for the workers panel.
     *
     * @return array{
     *     supervisor_running: bool,
     *     supervisor_status: string,
     *     program_name: string|null,
     *     configured_workers: int,
     *     running_workers: int,
     *     processes: list<array{pid: int, memory_mb: float, uptime: string, status: string}>,
     *     command: string|null,
     *     log_file: string|null,
     * }
     */
    public function getWorkerStats(): array
    {
        $username = $this->resolveUsername();
        $confPath = config('queue-monitor.workers.supervisor_conf_dir', '/etc/supervisor/conf.d');
        $confFile = rtrim((string) $confPath, '/').'/'.$username.'.conf';

        $supervisorInfo = $this->parseSupervisorConf($confFile);
        $systemctlOutput = $this->runSystemctlStatus();
        $supervisorRunning = $this->parseIsActive($systemctlOutput);
        $processes = $this->parseWorkerProcesses($systemctlOutput);

        return [
            'supervisor_running' => $supervisorRunning,
            'supervisor_status' => $this->parseSupervisorStatusLine($systemctlOutput),
            'program_name' => $supervisorInfo['program_name'],
            'configured_workers' => $supervisorInfo['numprocs'],
            'running_workers' => count($processes),
            'processes' => $processes,
            'command' => $supervisorInfo['command'],
            'log_file' => $supervisorInfo['log_file'],
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // SYSTEMCTL
    // ════════════════════════════════════════════════════════════════

    /**
     * Runs `systemctl status supervisor` via proc_open and returns stdout.
     * proc_open is available even when exec/shell_exec/popen are disabled.
     * Returns empty string on failure.
     */
    private function runSystemctlStatus(): string
    {
        if (! function_exists('proc_open')) {
            return '';
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            'systemctl status supervisor --no-pager -l 2>&1',
            $descriptors,
            $pipes,
        );

        if (! is_resource($process)) {
            return '';
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return is_string($output) ? $output : '';
    }

    /**
     * Parses "Active: active (running)" from systemctl output.
     */
    private function parseIsActive(string $output): bool
    {
        return (bool) preg_match('/Active:\s+active\s+\(running\)/', $output);
    }

    /**
     * Extracts a short human status from the Active line, e.g. "active (running)".
     */
    private function parseSupervisorStatusLine(string $output): string
    {
        if (preg_match('/Active:\s+(.+)/', $output, $m)) {
            // Trim " since ..." to keep it short
            $line = preg_replace('/\s*since\s+.+/', '', trim($m[1]));

            return $line ?? trim($m[1]);
        }

        return 'unknown';
    }

    /**
     * Parses the CGroup process tree from `systemctl status supervisor` output
     * and extracts queue:work processes that belong to this app.
     *
     * Matches both Unicode box-drawing chars (├└─│) used by systemctl in a UTF-8
     * locale AND their plain-ASCII fallbacks (+, -, |, \) used when LANG is not set
     * (which is common in PHP-FPM environments).
     *
     * Uses the short username path prefix for matching instead of the full artisan
     * path, since systemctl may wrap/truncate long command lines.
     *
     * @return list<array{pid: int, memory_mb: float, uptime: string, status: string}>
     */
    private function parseWorkerProcesses(string $output): array
    {
        $username = $this->resolveUsername();
        $userPath = '/home/'.$username.'/';
        $bootTime = $this->getBootTime();
        $hertz = $this->getClockHz();

        $workers = [];

        foreach (explode("\n", $output) as $line) {
            // Match cgroup tree lines with both Unicode box chars and ASCII fallbacks.
            // Unicode: ├ └ ─ │ (E2 94 xx) — used when LANG=*.UTF-8 is set.
            // ASCII:   + \ - |           — used when LANG is unset (typical in FPM).
            if (! preg_match('/[\x{251C}\x{2514}\x{2500}\x{2502}+|\\\\\s]+(\d+)\s+(.+)/u', $line, $m)) {
                continue;
            }

            $pid = (int) $m[1];
            $cmd = trim($m[2]);

            if (! str_contains($cmd, 'queue:work')) {
                continue;
            }

            // Match by username path prefix — short enough to never be truncated.
            if (! str_contains($cmd, $userPath)) {
                continue;
            }

            [$memMb, $uptime, $state] = $this->readProcInfo($pid, $bootTime, $hertz);

            $workers[] = [
                'pid' => $pid,
                'memory_mb' => $memMb,
                'uptime' => $uptime,
                'status' => $state,
            ];
        }

        usort($workers, fn (array $a, array $b): int => $a['pid'] <=> $b['pid']);

        return $workers;
    }

    // ════════════════════════════════════════════════════════════════
    // /proc HELPERS (supplemental — requires /proc in open_basedir)
    // ════════════════════════════════════════════════════════════════

    /**
     * Reads memory, uptime and state from /proc/{pid}/status + stat.
     * Returns defaults gracefully if /proc is not accessible.
     *
     * @return array{0: float, 1: string, 2: string} [memory_mb, uptime, status]
     */
    private function readProcInfo(int $pid, int $bootTime, int $hertz): array
    {
        $memMb = 0.0;
        $uptime = '—';
        $state = 'active';

        $statusRaw = @file_get_contents(sprintf('/proc/%d/status', $pid));
        if ($statusRaw !== false && preg_match('/^VmRSS:\s+(\d+)/m', $statusRaw, $m)) {
            $memMb = round((float) $m[1] / 1024, 1);
        }

        $statRaw = @file_get_contents(sprintf('/proc/%d/stat', $pid));
        if ($statRaw !== false) {
            // Remove "(comm)" field which may contain spaces, then split
            $statStr = preg_replace('/^\d+ \(.*?\) /', '', $statRaw) ?? '';
            $fields = explode(' ', trim($statStr));
            $stateChar = $fields[0];
            $state = $this->interpretState($stateChar);

            // Field index 19 in stripped string = starttime (22nd field in full stat)
            if (isset($fields[19]) && $bootTime > 0 && $hertz > 0) {
                $startTime = $bootTime + intdiv((int) $fields[19], $hertz);
                $uptime = $this->formatUptimeSecs(max(0, time() - $startTime));
            }
        }

        return [$memMb, $uptime, $state];
    }

    private function getBootTime(): int
    {
        $stat = @file_get_contents('/proc/stat');
        if ($stat && preg_match('/^btime\s+(\d+)/m', $stat, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function getClockHz(): int
    {
        if (function_exists('posix_sysconf') && defined('POSIX_SC_CLK_TCK')) {
            $hz = posix_sysconf(POSIX_SC_CLK_TCK);
            if ($hz > 0) {
                return $hz;
            }
        }

        return 100;
    }

    // ════════════════════════════════════════════════════════════════
    // SUPERVISOR CONF PARSER
    // ════════════════════════════════════════════════════════════════

    /**
     * @return array{program_name: string|null, numprocs: int, command: string|null, log_file: string|null}
     */
    private function parseSupervisorConf(string $confFile): array
    {
        $result = ['program_name' => null, 'numprocs' => 0, 'command' => null, 'log_file' => null];

        $content = @file_get_contents($confFile);
        if ($content === false) {
            return $result;
        }

        if (preg_match('/^\[program:(.+?)\]/m', $content, $m)) {
            $result['program_name'] = trim($m[1]);
        }

        if (preg_match('/^numprocs\s*=\s*(\d+)/m', $content, $m)) {
            $result['numprocs'] = (int) $m[1];
        }

        if (preg_match('/^command\s*=\s*(.+)/m', $content, $m)) {
            $result['command'] = trim($m[1]);
        }

        if (preg_match('/^stdout_logfile\s*=\s*(.+)/m', $content, $m)) {
            $result['log_file'] = trim($m[1]);
        }

        return $result;
    }

    // ════════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════════

    private function resolveUsername(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && (isset($info['name']) && ($info['name'] !== '' && $info['name'] !== '0'))) {
                return $info['name'];
            }
        }

        if (preg_match('|^/home/([^/]+)/|', base_path(), $m)) {
            return $m[1];
        }

        return '';
    }

    private function formatUptimeSecs(int $secs): string
    {
        if ($secs <= 0) {
            return '< 1s';
        }

        $days = intdiv($secs, 86400);
        $hours = intdiv($secs % 86400, 3600);
        $minutes = intdiv($secs % 3600, 60);
        $seconds = $secs % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        if ($seconds > 0 || $parts === []) {
            $parts[] = $seconds.'s';
        }

        return implode(' ', $parts);
    }

    private function interpretState(string $state): string
    {
        return match ($state) {
            'R' => 'running',
            'S' => 'idle',
            'D' => 'waiting',
            'Z' => 'zombie',
            'T', 't' => 'stopped',
            default => strtolower($state),
        };
    }
}

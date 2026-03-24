<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

trait HandlesBashOperations
{
    private const BASH_DEFAULT_TIMEOUT_MS = 120000;

    private const BASH_MAX_OUTPUT_LINES = 2000;

    private const BASH_MAX_OUTPUT_BYTES = 51200;

    public function bash(string $command, string $description, ?string $workdir = null, ?int $timeout = null): string
    {
        $trimmedCommand = trim($command);
        $trimmedDescription = trim($description);

        if ($trimmedCommand === '') {
            throw new RuntimeException('command is required');
        }

        if ($trimmedDescription === '') {
            throw new RuntimeException('description is required');
        }

        $timeoutMs = $timeout ?? self::BASH_DEFAULT_TIMEOUT_MS;

        if ($timeoutMs <= 0) {
            throw new RuntimeException('timeout must be greater than 0 milliseconds');
        }

        $workingDirectory = $this->resolveBashWorkingDirectory($workdir);
        $shellBinary = $this->resolveBashBinary();
        $process = new Process([$shellBinary, '-lc', $trimmedCommand], $workingDirectory, $this->bashEnvironment(), null, $timeoutMs / 1000);
        $output = '';
        $timedOut = false;

        try {
            $process->run(function (string $type, string $buffer) use (&$output): void {
                $output .= $buffer;
            });
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        }

        $renderedOutput = $this->truncateBashOutput($output);
        $lines = [
            'Description: '.$trimmedDescription,
            'Command: '.$trimmedCommand,
            'Workdir: '.$this->relativePath($workingDirectory),
            'Exit code: '.($timedOut ? 'timeout' : (string) ($process->getExitCode() ?? 1)),
            'Output:',
            $renderedOutput,
        ];

        if ($timedOut) {
            $lines[] = '';
            $lines[] = '<bash_metadata>';
            $lines[] = sprintf('bash tool terminated command after exceeding timeout %d ms', $timeoutMs);
            $lines[] = '</bash_metadata>';
        }

        return implode("\n", $lines);
    }

    private function resolveBashWorkingDirectory(?string $workdir): string
    {
        if ($workdir === null || trim($workdir) === '') {
            return $this->workspaceRoot();
        }

        $directoryPath = $this->resolveExistingPath($workdir);

        if (! is_dir($directoryPath)) {
            throw new RuntimeException('workdir must point to an existing directory');
        }

        return $directoryPath;
    }

    private function resolveBashBinary(): string
    {
        foreach (['/bin/bash', '/usr/bin/bash'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'bash';
    }

    /**
     * @return array<string, string>|null
     */
    private function bashEnvironment(): ?array
    {
        $environment = getenv();

        return is_array($environment) ? $environment : null;
    }

    private function truncateBashOutput(string $output): string
    {
        if ($output === '') {
            return '(no output)';
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $truncatedLines = [];
        $bytes = 0;
        $truncated = false;

        foreach ($lines as $index => $line) {
            if ($index >= self::BASH_MAX_OUTPUT_LINES) {
                $truncated = true;

                break;
            }

            $lineBytes = strlen($line) + ($index > 0 ? 1 : 0);

            if (($bytes + $lineBytes) > self::BASH_MAX_OUTPUT_BYTES) {
                $truncated = true;

                break;
            }

            $truncatedLines[] = $line;
            $bytes += $lineBytes;
        }

        $preview = implode("\n", $truncatedLines);

        if (! $truncated) {
            return rtrim($preview, "\n");
        }

        $outputPath = $this->persistBashOutput($output);
        $relativeOutputPath = $this->relativePath($outputPath);

        return rtrim($preview, "\n")
            ."\n\n...output truncated...\n\n"
            ."Full output saved to: {$relativeOutputPath}\n"
            .'Use read with offset/limit or grep to inspect the saved output.';
    }

    private function persistBashOutput(string $output): string
    {
        $directory = storage_path('app/chatbot/tool-output');
        File::ensureDirectoryExists($directory);

        $filename = sprintf('tool_%s_%s.log', now()->format('Ymd_His'), bin2hex(random_bytes(4)));
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        File::put($path, $output);

        return $this->normalizePath($path);
    }
}

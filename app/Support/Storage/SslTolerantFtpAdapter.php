<?php

namespace App\Support\Storage;

use League\Flysystem\Config;
use League\Flysystem\Ftp\FtpAdapter;

/**
 * FTP adapter that suppresses harmless SSL shutdown warnings.
 *
 * Many FTPS servers (e.g. BunnyCDN) don't perform a clean SSL shutdown,
 * causing PHP to emit: "SSL_read on shutdown: unexpected eof while reading".
 * The file transfer actually succeeds — the warning only fires during the
 * TLS teardown of the data channel after the upload completes.
 *
 * This adapter wraps write operations with a temporary error handler that
 * silences that specific warning while letting all other errors propagate.
 */
class SslTolerantFtpAdapter extends FtpAdapter
{
    public function write(string $path, string $contents, Config $config): void
    {
        $this->withSslWarningsSuppressed(fn () => parent::write($path, $contents, $config));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->withSslWarningsSuppressed(fn () => parent::writeStream($path, $contents, $config));
    }

    /**
     * Execute a callback while suppressing harmless FTPS SSL shutdown warnings.
     */
    private function withSslWarningsSuppressed(callable $callback): void
    {
        $previousHandler = set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$previousHandler) {
            // Suppress only the specific SSL shutdown warning
            if ($severity === E_WARNING && str_contains($message, 'SSL_read on shutdown')) {
                return true; // handled, don't propagate
            }

            // Let everything else through to the previous handler
            if ($previousHandler !== null) {
                return $previousHandler($severity, $message, $file, $line);
            }

            return false; // use PHP's default error handling
        });

        try {
            $callback();
        } finally {
            restore_error_handler();
        }
    }
}

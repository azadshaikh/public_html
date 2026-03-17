<?php

namespace Modules\Platform\Services;

use InvalidArgumentException;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\RSA;

/**
 * Service for generating and managing SSH key pairs.
 *
 * Uses phpseclib3 for key generation in OpenSSH format (Ed25519 by default).
 */
class SSHKeyService
{
    /**
     * Default key size in bits.
     */
    public const DEFAULT_KEY_SIZE = 4096;

    /**
     * Default key type.
     */
    public const DEFAULT_KEY_TYPE = 'ed25519';

    /**
     * Default OpenSSH key comment.
     */
    public const DEFAULT_KEY_COMMENT = 'astero-generated-key';

    /**
     * Generate a new SSH key pair.
     *
     * @param  int  $keySize  Key size in bits (RSA only, default: 4096)
     * @param  string  $comment  Key comment to include in OpenSSH output
     * @param  string  $type  Key type: ed25519|rsa
     * @return array ['public_key' => string, 'private_key' => string, 'fingerprint' => string]
     */
    public function generateKeyPair(
        int $keySize = self::DEFAULT_KEY_SIZE,
        string $comment = self::DEFAULT_KEY_COMMENT,
        string $type = self::DEFAULT_KEY_TYPE
    ): array {
        $type = strtolower($type);

        if ($type === 'ed25519') {
            $privateKey = EC::createKey('Ed25519');
        } elseif ($type === 'rsa') {
            $privateKey = RSA::createKey($keySize);
        } else {
            throw new InvalidArgumentException('Unsupported SSH key type: '.$type);
        }

        $formatOptions = ['comment' => $comment];
        $publicKeyString = $privateKey->getPublicKey()->toString('OpenSSH', $formatOptions);
        $privateKeyString = $privateKey->toString('OpenSSH', $formatOptions);

        return [
            'public_key' => $publicKeyString,
            'private_key' => $privateKeyString,
            'fingerprint' => $this->calculateFingerprint($publicKeyString),
        ];
    }

    /**
     * Generate the SSH command to add public key to authorized_keys.
     *
     * @param  string  $publicKey  The SSH public key
     * @param  string  $comment  Optional comment to append to key
     * @return string The command to run on the server
     */
    public function generateAuthorizedKeysCommand(string $publicKey, string $comment = ''): string
    {
        // Append comment if provided (e.g., "astero-generated-key")
        $keyWithComment = trim($publicKey);
        if ($comment && ! str_contains($keyWithComment, ' ')) {
            $keyWithComment .= ' '.$comment;
        }

        // Build the command that creates .ssh directory and adds the key
        return sprintf(
            'mkdir -p /root/.ssh && touch /root/.ssh/authorized_keys && echo "%s" >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys && chmod 700 /root/.ssh',
            addslashes($keyWithComment)
        );
    }

    /**
     * Calculate SHA256 fingerprint of a public key.
     *
     * @param  string  $publicKey  The SSH public key
     * @return string The fingerprint in SHA256:base64 format
     */
    public function calculateFingerprint(string $publicKey): string
    {
        // Extract the key data (second part of "ssh-rsa AAAAB3... comment")
        $parts = explode(' ', trim($publicKey));
        if (count($parts) < 2) {
            return '';
        }

        $keyData = base64_decode($parts[1]);
        $hash = hash('sha256', $keyData, true);

        return 'SHA256:'.rtrim(base64_encode($hash), '=');
    }

    /**
     * Validate an SSH public key format.
     */
    public function isValidPublicKey(string $publicKey): bool
    {
        $publicKey = trim($publicKey);

        // Must start with ssh-rsa, ssh-ed25519, or ecdsa-
        if (! preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-\w+)\s+/', $publicKey)) {
            return false;
        }

        // Must have at least key type and key data
        $parts = explode(' ', $publicKey);
        if (count($parts) < 2) {
            return false;
        }

        $keyData = base64_decode($parts[1], true);

        return $keyData !== false && $keyData !== '';
    }

    /**
     * Validate an SSH private key format.
     */
    public function isValidPrivateKey(string $privateKey): bool
    {
        $privateKey = trim($privateKey);

        // Check for common private key headers
        return str_contains($privateKey, '-----BEGIN') &&
               str_contains($privateKey, 'PRIVATE KEY-----');
    }
}

<?php

namespace App\Services;

use App\Models\User;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class TwoFactorAuthenticationService
{
    private const int OTP_DIGITS = 6;

    private const int OTP_PERIOD_SECONDS = 30;

    private const int RECOVERY_CODES_COUNT = 8;

    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function beginSetup(User $user): string
    {
        $secret = $this->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    public function confirmSetup(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if (! $this->verifySecret($user->two_factor_secret, $this->normalizeOtpCode($code))) {
            return false;
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return $recoveryCodes;
    }

    public function isEnabled(User $user): bool
    {
        return filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null;
    }

    public function hasPendingSetup(User $user): bool
    {
        return filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;
    }

    /**
     * @return array<int, string>
     */
    public function getRecoveryCodes(User $user): array
    {
        $storedCodes = $user->getAttribute('two_factor_recovery_codes');
        $decodedCodes = is_string($storedCodes) ? json_decode($storedCodes, true) : $storedCodes;

        return is_array($decodedCodes) ? array_values($decodedCodes) : [];
    }

    public function getOtpAuthUrl(User $user): ?string
    {
        if (! $user->two_factor_secret) {
            return null;
        }

        $issuer = (string) config('app.name', 'Astero');
        $issuerEncoded = rawurlencode($issuer);
        $email = rawurlencode((string) $user->email);

        return sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30', $issuerEncoded, $email, $user->two_factor_secret, $issuerEncoded);
    }

    public function getQrCodeDataUri(string $data): string
    {
        $options = new QROptions([
            'eccLevel' => QRCode::ECC_M,
            'scale' => 6,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $this->isEnabled($user)) {
            return false;
        }

        return $this->verifySecret((string) $user->two_factor_secret, $this->normalizeOtpCode($code));
    }

    public function consumeRecoveryCode(User $user, string $recoveryCode): bool
    {
        if (! $this->isEnabled($user)) {
            return false;
        }

        $normalizedInput = $this->normalizeRecoveryCode($recoveryCode);
        if ($normalizedInput === '') {
            return false;
        }

        $recoveryCodes = $this->getRecoveryCodes($user);
        $updatedCodes = [];
        $matched = false;

        foreach ($recoveryCodes as $code) {
            $normalizedCode = $this->normalizeRecoveryCode($code);

            if (! $matched && hash_equals($normalizedCode, $normalizedInput)) {
                $matched = true;

                continue;
            }

            $updatedCodes[] = $code;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $updatedCodes,
        ])->save();

        return true;
    }

    public function generateCodeForSecret(string $secret, ?int $timestamp = null): string
    {
        return $this->generateOneTimePassword($secret, (int) floor(($timestamp ?? time()) / self::OTP_PERIOD_SECONDS));
    }

    private function verifySecret(string $secret, string $code, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $currentSlice = (int) floor(time() / self::OTP_PERIOD_SECONDS);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expectedCode = $this->generateOneTimePassword($secret, $currentSlice + $offset);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateOneTimePassword(string $secret, int $timeSlice): string
    {
        $secretKey = $this->decodeBase32($secret);
        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $binaryTime, $secretKey, true);
        $offset = ord($hash[19]) & 0x0F;

        $value = (((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)) % (10 ** self::OTP_DIGITS);

        return str_pad((string) $value, self::OTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function generateSecret(int $length = 32): string
    {
        $secret = '';
        $maxIndex = strlen(self::BASE32_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $maxIndex)];
        }

        return $secret;
    }

    private function decodeBase32(string $base32): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32) ?? '');
        $binaryString = '';

        foreach (str_split($clean) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);
            if ($position === false) {
                continue;
            }

            $binaryString .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $result = '';

        foreach (str_split($binaryString, 8) as $byte) {
            if (strlen($byte) === 8) {
                $result .= chr(bindec($byte));
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $chunk = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($chunk, 0, 4).'-'.substr($chunk, 4, 4);
        }

        return $codes;
    }

    private function normalizeOtpCode(string $code): string
    {
        return preg_replace('/\D+/', '', $code) ?? '';
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }
}

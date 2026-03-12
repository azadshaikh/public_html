<?php

namespace App\Services;

class SiteAccessProtectionService
{
    /**
     * Clear site access protection session data.
     */
    public function clearSiteAccessProtectionSession(): void
    {
        session()->forget([
            'site_access_protection_verified',
            'site_access_protection_intended_url',
            'password_protection_verified',
            'password_protection_intended_url',
        ]);
    }

    /**
     * Check if site access protection is enabled.
     */
    public function isSiteAccessProtectionEnabled(): bool
    {
        return filter_var(
            setting('site_access_protection_mode_enabled', setting('password_protected_mode_enabled', false)),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Check if site access protection is verified in the current session.
     */
    public function isSiteAccessVerified(): bool
    {
        return session('site_access_protection_verified', session('password_protection_verified', false));
    }

    /**
     * Mark site access protection as verified in session.
     */
    public function markSiteAccessAsVerified(): void
    {
        session([
            'site_access_protection_verified' => true,
            'password_protection_verified' => true,
        ]);
    }

    /**
     * Store the intended URL for redirect after verification.
     */
    public function storeIntendedUrl(string $url): void
    {
        session([
            'site_access_protection_intended_url' => $url,
            'password_protection_intended_url' => $url,
        ]);
    }

    /**
     * Get and clear the intended URL.
     */
    public function getAndClearIntendedUrl(string $default = '/'): string
    {
        $intendedUrl = session('site_access_protection_intended_url', session('password_protection_intended_url', $default));
        session()->forget(['site_access_protection_intended_url', 'password_protection_intended_url']);

        return $intendedUrl;
    }
}

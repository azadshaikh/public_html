<?php

namespace Modules\CMS\Services;

use Illuminate\Support\Facades\Hash;
use Modules\CMS\Models\CmsPost;

class PostAccessProtectionService
{
    /**
     * Session key prefix for post password verification.
     */
    private const string SESSION_PREFIX = 'post_password_verified_';

    /**
     * Session key prefix for intended URL.
     */
    private const string INTENDED_URL_PREFIX = 'post_password_intended_url_';

    /**
     * Check if post password is verified in session.
     */
    public function isPostAccessVerified(int $postId): bool
    {
        return session(self::SESSION_PREFIX.$postId, false);
    }

    /**
     * Mark post access as verified in session.
     */
    public function markPostAccessAsVerified(int $postId): void
    {
        session([self::SESSION_PREFIX.$postId => true]);
    }

    /**
     * Clear post access verification from session.
     */
    public function clearPostAccessVerification(int $postId): void
    {
        session()->forget(self::SESSION_PREFIX.$postId);
    }

    /**
     * Store intended URL for redirect after verification.
     */
    public function storeIntendedUrl(string $url, int $postId): void
    {
        session([self::INTENDED_URL_PREFIX.$postId => $this->sanitizeIntendedUrl($url)]);
    }

    /**
     * Get and clear intended URL.
     */
    public function getAndClearIntendedUrl(int $postId, string $default = '/'): string
    {
        $key = self::INTENDED_URL_PREFIX.$postId;
        $url = $this->sanitizeIntendedUrl((string) session($key, $default));
        session()->forget($key);

        return $url;
    }

    /**
     * Verify password against post.
     */
    public function verifyPassword(CmsPost $post, string $password): bool
    {
        if (empty($post->post_password)) {
            return false;
        }

        return Hash::check($password, $post->post_password);
    }

    /**
     * Check if a post requires password verification.
     */
    public function requiresPasswordVerification(CmsPost $post): bool
    {
        return $post->isPasswordProtected() && ! $this->isPostAccessVerified($post->id);
    }

    /**
     * Only allow internal paths to prevent open redirects.
     */
    private function sanitizeIntendedUrl(string $url): string
    {
        $fallback = '/';
        $url = trim($url);
        if ($url === '') {
            return $fallback;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return $fallback;
        }

        if (isset($parsed['host'])) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($appHost && strcasecmp($parsed['host'], $appHost) !== 0) {
                return $fallback;
            }

            $path = $parsed['path'] ?? '/';
            $path = '/'.ltrim($path, '/');
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
            $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

            return $path.$query.$fragment;
        }

        if (str_starts_with($url, '/')) {
            return '/'.ltrim($url, '/');
        }

        return $fallback;
    }
}

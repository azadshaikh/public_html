<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Unpoly Support Class
 *
 * Provides helper methods for integrating with Unpoly SPA navigation.
 * Handles flash messages, redirects, and response headers for Unpoly requests.
 */
class Unpoly
{
    /**
     * Check if current request is an Unpoly request
     */
    public static function isUnpolyRequest(): bool
    {
        return request()->hasHeader('X-Up-Target');
    }

    /**
     * Get the Unpoly target selector
     */
    public static function getTarget(): ?string
    {
        return request()->header('X-Up-Target');
    }

    /**
     * Get the Unpoly mode (root, modal, drawer, etc.)
     */
    public static function getMode(): string
    {
        return request()->header('X-Up-Mode', 'root');
    }

    /**
     * Check if request is targeting a modal layer
     */
    public static function isModal(): bool
    {
        return self::getMode() === 'modal';
    }

    /**
     * Check if request is targeting a drawer layer
     */
    public static function isDrawer(): bool
    {
        return self::getMode() === 'drawer';
    }

    /**
     * Check if request is targeting the root layer
     */
    public static function isRoot(): bool
    {
        return self::getMode() === 'root';
    }

    /**
     * Add flash message headers to a response
     *
     * @param  SymfonyResponse  $response  The response to add headers to
     * @param  string  $message  The flash message
     * @param  string  $type  The message type (success, error, warning, info)
     */
    public static function addFlashHeaders(SymfonyResponse $response, string $message, string $type = 'info'): SymfonyResponse
    {
        $response->headers->set('X-Flash-Message', rawurlencode($message));
        $response->headers->set('X-Flash-Type', $type);

        return $response;
    }

    /**
     * Add validation error headers to a response
     *
     * @param  SymfonyResponse  $response  The response to add headers to
     * @param  array  $errors  The validation errors
     */
    public static function addValidationErrorHeaders(SymfonyResponse $response, array $errors): SymfonyResponse
    {
        $response->headers->set('X-Validation-Errors', rawurlencode(json_encode($errors)));

        return $response;
    }

    /**
     * Create a redirect response with flash headers for Unpoly
     *
     * @param  string  $url  The URL to redirect to
     * @param  string|null  $message  Optional flash message
     * @param  string  $type  Message type (success, error, warning, info)
     */
    public static function redirect(string $url, ?string $message = null, string $type = 'success'): RedirectResponse
    {
        $response = redirect($url);

        if ($message && self::isUnpolyRequest()) {
            self::addFlashHeaders($response, $message, $type);
        } elseif ($message) {
            $response->with($type, $message);
        }

        return $response;
    }

    /**
     * Create a redirect response with success message
     */
    public static function redirectWithSuccess(string $url, string $message): RedirectResponse
    {
        return self::redirect($url, $message, 'success');
    }

    /**
     * Create a redirect response with error message
     */
    public static function redirectWithError(string $url, string $message): RedirectResponse
    {
        return self::redirect($url, $message, 'error');
    }

    /**
     * Create a redirect response with warning message
     */
    public static function redirectWithWarning(string $url, string $message): RedirectResponse
    {
        return self::redirect($url, $message, 'warning');
    }

    /**
     * Create a redirect response with info message
     */
    public static function redirectWithInfo(string $url, string $message): RedirectResponse
    {
        return self::redirect($url, $message, 'info');
    }

    /**
     * Close the current layer (modal/drawer) and optionally emit an event
     *
     * @param  mixed  $value  Value to pass to the layer's accept handler
     */
    public static function closeLayer(mixed $value = null): Response
    {
        $response = response('', 200);
        $response->headers->set('X-Up-Accept-Layer', json_encode($value));

        return $response;
    }

    /**
     * Dismiss the current layer (modal/drawer) without accepting
     *
     * @param  mixed  $value  Value to pass to the layer's dismiss handler
     */
    public static function dismissLayer(mixed $value = null): Response
    {
        $response = response('', 200);
        $response->headers->set('X-Up-Dismiss-Layer', json_encode($value));

        return $response;
    }

    /**
     * Emit an event from the server to the client
     *
     * @param  string  $eventName  The event name
     * @param  array  $data  Event data
     */
    public static function emit(string $eventName, array $data = []): array
    {
        return [
            'X-Up-Events' => json_encode([
                ['type' => $eventName, 'data' => $data],
            ]),
        ];
    }

    /**
     * Force a full page reload instead of partial update
     */
    public static function forceReload(): Response
    {
        $response = response('', 200);
        $response->headers->set('X-Up-Location', request()->fullUrl());
        $response->headers->set('X-Up-Method', 'GET');

        return $response;
    }

    /**
     * Force Unpoly to navigate to a URL.
     *
     * This is useful when the server would otherwise respond with a 302 that
     * is followed inside XHR (e.g. session expiry -> login), making the UI feel
     * stuck.
     */
    public static function navigate(string $url, string $method = 'GET'): Response
    {
        $response = response('', 200);
        $response->headers->set('X-Up-Location', $url);
        $response->headers->set('X-Up-Method', strtoupper($method));

        return $response;
    }

    /**
     * Update the browser's URL without changing the content
     */
    public static function updateUrl(string $url): array
    {
        return [
            'X-Up-Location' => $url,
        ];
    }

    /**
     * Clear the Unpoly cache
     */
    public static function clearCache(): array
    {
        return [
            'X-Up-Expire-Cache' => '*',
            'X-Up-Clear-Cache' => '*',
        ];
    }

    /**
     * Set the document title from the server
     */
    public static function setTitle(string $title): array
    {
        return [
            'X-Up-Title' => $title,
        ];
    }

    /**
     * Get the fail target (where to render on failure)
     */
    public static function getFailTarget(): ?string
    {
        return request()->header('X-Up-Fail-Target');
    }

    /**
     * Check if the request prefers a specific target
     */
    public static function prefersTarget(string $selector): bool
    {
        $target = self::getTarget();

        return $target && str_contains($target, $selector);
    }
}

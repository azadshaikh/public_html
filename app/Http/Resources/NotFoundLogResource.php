<?php

namespace App\Http\Resources;

use App\Models\NotFoundLog;
use App\Models\User;
use App\Services\GeoIpService;
use App\Traits\DateTimeFormattingTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class NotFoundLogResource extends JsonResource
{
    use DateTimeFormattingTrait;

    public function toArray(Request $request): array
    {
        $notFoundLog = $this->notFoundLog();
        $url = $notFoundLog->getAttribute('url');
        $referer = $notFoundLog->getAttribute('referer');
        $userAgent = $notFoundLog->getAttribute('user_agent');
        $isBot = (bool) $notFoundLog->getAttribute('is_bot');
        $isSuspicious = (bool) $notFoundLog->getAttribute('is_suspicious');
        $createdAt = $notFoundLog->getAttribute('created_at');
        $userRelation = $notFoundLog->user()->first();
        $user = $userRelation instanceof User ? $userRelation : null;
        $userName = $user instanceof User
            ? (string) ($user->getAttribute('full_name') ?? $user->getAttribute('name') ?? '-')
            : '-';

        $data = [
            'id' => $notFoundLog->getKey(),
            'checkbox' => true,
            'show_url' => route('app.logs.not-found-logs.show', $notFoundLog->getKey()),
            'url' => $url,
            'url_display' => $this->truncateUrl(is_string($url) ? $url : null, 50),
            'full_url' => $notFoundLog->getAttribute('full_url'),
            'referer' => $referer,
            'referer_display' => is_string($referer) && $referer !== '' ? $this->truncateUrl($referer, 40) : '-',
            'ip_address' => $this->getIpAddressDisplay($notFoundLog),
            'ip_address_raw' => $notFoundLog->getAttribute('ip_address'),
            'user_agent' => $userAgent,
            'method' => $notFoundLog->getAttribute('method'),
            'is_bot' => $isBot,
            'is_suspicious' => $isSuspicious,
            'user_id' => $notFoundLog->getAttribute('user_id'),
            'user_name' => $userName,
            'browser' => $this->parseBrowser(is_string($userAgent) ? $userAgent : null),
            'metadata' => $notFoundLog->getAttribute('metadata'),
            // Badge template expects {column}_label and {column}_class
            'status_badge' => $this->getStatusLabel($isSuspicious, $isBot),
            'status_badge_label' => $this->getStatusLabel($isSuspicious, $isBot),
            'status_badge_class' => $this->getStatusClass($isSuspicious, $isBot),
            'created_at' => $createdAt,
            'time_ago' => method_exists($createdAt, 'diffForHumans') ? $createdAt->diffForHumans() : null,
            'actions' => $this->buildActions($notFoundLog),
        ];

        return $this->formatDateTimeFields(
            $data,
            datetimeFields: ['created_at']
        );
    }

    /**
     * Get IP address with country flag
     */
    private function getIpAddressDisplay(NotFoundLog $notFoundLog): string
    {
        $ipAddress = $notFoundLog->getAttribute('ip_address');
        if (! is_string($ipAddress) || $ipAddress === '') {
            return '-';
        }

        $geoIpService = resolve(GeoIpService::class);
        $country = $geoIpService->getCountryFromIp($ipAddress);

        if ($country && ! empty($country['iso_code'])) {
            $flag = $this->iso2ToFlag($country['iso_code']);

            return sprintf('%s %s', $flag, $ipAddress);
        }

        return $ipAddress;
    }

    /**
     * Convert ISO2 country code to flag emoji
     */
    private function iso2ToFlag(string $iso2): string
    {
        if (strlen($iso2) !== 2) {
            return '🌍';
        }

        $iso2 = strtoupper($iso2);
        $flagOffset = 0x1F1E6;
        $asciiOffset = ord('A');

        $firstChar = ord($iso2[0]) - $asciiOffset + $flagOffset;
        $secondChar = ord($iso2[1]) - $asciiOffset + $flagOffset;

        return mb_chr($firstChar).mb_chr($secondChar);
    }

    /**
     * Get status label based on is_bot and is_suspicious flags.
     */
    private function getStatusLabel(bool $isSuspicious, bool $isBot): string
    {
        if ($isSuspicious) {
            return 'Suspicious';
        }

        if ($isBot) {
            return 'Bot';
        }

        return 'Human';
    }

    /**
     * Get status CSS class based on is_bot and is_suspicious flags.
     */
    private function getStatusClass(bool $isSuspicious, bool $isBot): string
    {
        if ($isSuspicious) {
            return 'bg-danger-subtle text-danger';
        }

        if ($isBot) {
            return 'bg-warning-subtle text-warning';
        }

        return 'bg-success-subtle text-success';
    }

    /**
     * Truncate URL for display.
     */
    private function truncateUrl(?string $url, int $maxLength = 50): string
    {
        if (in_array($url, [null, '', '0'], true)) {
            return '-';
        }

        if (strlen($url) <= $maxLength) {
            return $url;
        }

        return substr($url, 0, $maxLength - 3).'...';
    }

    /**
     * Parse browser from user agent.
     */
    private function parseBrowser(?string $userAgent): string
    {
        if (in_array($userAgent, [null, '', '0'], true)) {
            return 'Unknown';
        }

        $userAgentLower = strtolower($userAgent);

        // Check for common browsers
        if (str_contains($userAgentLower, 'firefox')) {
            return 'Firefox';
        }

        if (str_contains($userAgentLower, 'edg')) {
            return 'Edge';
        }

        if (str_contains($userAgentLower, 'chrome')) {
            return 'Chrome';
        }

        if (str_contains($userAgentLower, 'safari')) {
            return 'Safari';
        }

        if (str_contains($userAgentLower, 'opera') || str_contains($userAgentLower, 'opr')) {
            return 'Opera';
        }

        // Check for bots
        if (str_contains($userAgentLower, 'googlebot')) {
            return 'Googlebot';
        }

        if (str_contains($userAgentLower, 'bingbot')) {
            return 'Bingbot';
        }

        if (str_contains($userAgentLower, 'bot') || str_contains($userAgentLower, 'crawler')) {
            return 'Bot';
        }

        // Check for tools
        if (str_contains($userAgentLower, 'curl')) {
            return 'cURL';
        }

        if (str_contains($userAgentLower, 'wget')) {
            return 'Wget';
        }

        if (str_contains($userAgentLower, 'python')) {
            return 'Python';
        }

        return 'Other';
    }

    /**
     * Build actions array
     */
    private function buildActions(NotFoundLog $notFoundLog): array
    {
        $actions = [];
        $isTrashed = $notFoundLog->trashed();

        // View action
        $actions['view'] = [
            'url' => route('app.logs.not-found-logs.show', $notFoundLog->getKey()),
            'label' => 'View Details',
            'icon' => 'ri-eye-line',
            'class' => 'dropdown-item',
            'method' => 'GET',
        ];

        if ($isTrashed) {
            $actions['restore'] = [
                'url' => route('app.logs.not-found-logs.restore', $notFoundLog->getKey()),
                'label' => 'Restore',
                'icon' => 'ri-refresh-line',
                'class' => 'dropdown-item text-success',
                'method' => 'PATCH',
                'confirm' => 'Restore this 404 log?',
            ];

            $actions['force_delete'] = [
                'url' => route('app.logs.not-found-logs.force-delete', $notFoundLog->getKey()),
                'label' => 'Delete Permanently',
                'icon' => 'ri-delete-bin-fill',
                'class' => 'dropdown-item text-danger',
                'method' => 'DELETE',
                'confirm' => '⚠️ PERMANENT: This 404 log will be deleted forever and cannot be recovered.',
            ];
        } else {
            // Delete action
            $actions['delete'] = [
                'url' => route('app.logs.not-found-logs.destroy', $notFoundLog->getKey()),
                'label' => 'Delete',
                'icon' => 'ri-delete-bin-line',
                'class' => 'dropdown-item text-danger',
                'method' => 'DELETE',
                'confirm' => 'Are you sure you want to delete this 404 log record?',
            ];
        }

        return $actions;
    }

    private function notFoundLog(): NotFoundLog
    {
        throw_unless($this->resource instanceof NotFoundLog, RuntimeException::class, 'NotFoundLogResource expects a NotFoundLog model instance.');

        return $this->resource;
    }
}

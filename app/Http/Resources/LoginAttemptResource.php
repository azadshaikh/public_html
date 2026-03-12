<?php

namespace App\Http\Resources;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\GeoIpService;
use App\Traits\DateTimeFormattingTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class LoginAttemptResource extends JsonResource
{
    use DateTimeFormattingTrait;

    public function toArray(Request $request): array
    {
        $loginAttempt = $this->loginAttempt();
        $status = (string) $loginAttempt->getAttribute('status');
        $failureReason = $loginAttempt->getAttribute('failure_reason');
        $createdAt = $loginAttempt->getAttribute('created_at');
        $userRelation = $loginAttempt->user()->first();
        $user = $userRelation instanceof User ? $userRelation : null;

        $data = [
            'id' => $loginAttempt->getKey(),
            'checkbox' => true,
            'show_url' => route('app.logs.login-attempts.show', $loginAttempt->getKey()),
            'email' => $loginAttempt->getAttribute('email'),
            'ip_address' => $this->getIpAddressDisplay($loginAttempt),
            'ip_address_raw' => $loginAttempt->getAttribute('ip_address'),
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'status_class' => $this->getStatusClass($status),
            'status_badge' => $this->getStatusBadge($status),
            'failure_reason' => $failureReason,
            'failure_reason_label' => $this->getFailureReasonLabel($status, is_string($failureReason) ? $failureReason : null),
            'user_id' => $loginAttempt->getAttribute('user_id'),
            'user_name' => $user instanceof User ? $user->name : '-',
            'user_agent' => $loginAttempt->getAttribute('user_agent'),
            'browser' => $this->getBrowserName($loginAttempt->getAttribute('user_agent')),
            'metadata' => $loginAttempt->getAttribute('metadata'),
            'created_at' => $createdAt,
            'time_ago' => method_exists($createdAt, 'diffForHumans') ? $createdAt->diffForHumans() : null,
            'actions' => $this->buildActions($loginAttempt),
        ];

        return $this->formatDateTimeFields(
            $data,
            datetimeFields: ['created_at']
        );
    }

    /**
     * Get IP address with country flag
     */
    private function getIpAddressDisplay(LoginAttempt $loginAttempt): string
    {
        $ipAddress = $loginAttempt->getAttribute('ip_address');
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
     * Get status badge HTML
     */
    private function getStatusBadge(string $status): string
    {
        return match ($status) {
            'success' => '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Success</span>',
            'failed' => '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>Failed</span>',
            'blocked' => '<span class="badge bg-warning-subtle text-warning"><i class="ri-forbid-line me-1"></i>Blocked</span>',
            'cleared' => '<span class="badge bg-info-subtle text-info"><i class="ri-lock-unlock-line me-1"></i>Cleared</span>',
            default => '<span class="badge text-bg-secondary-subtle text-secondary">Unknown</span>',
        };
    }

    /**
     * Get status label for DataGrid badges
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => 'Successful',
            'failed' => 'Failed',
            'blocked' => 'Blocked',
            'cleared' => 'Cleared',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get status CSS class for DataGrid badges
     */
    private function getStatusClass(string $status): string
    {
        return match ($status) {
            'success' => 'bg-success-subtle text-success',
            'failed' => 'bg-danger-subtle text-danger',
            'blocked' => 'bg-warning-subtle text-warning',
            'cleared' => 'bg-info-subtle text-info',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    /**
     * Get human-readable failure reason
     */
    private function getFailureReasonLabel(string $status, ?string $failureReason): string
    {
        // For successful attempts, show "Success" instead of "-"
        if ($status === 'success') {
            return 'Success';
        }

        if ($failureReason === null || $failureReason === '') {
            return '-';
        }

        return match ($failureReason) {
            'invalid_credentials' => 'Invalid Credentials',
            'account_suspended' => 'Account Suspended',
            'account_banned' => 'Account Banned',
            'account_pending' => 'Account Pending',
            'rate_limited' => 'Rate Limited',
            'email_not_verified' => 'Email Not Verified',
            'social_auth_failed' => 'Social Login Failed',
            'social_email_missing' => 'Social Email Missing',
            default => ucwords(str_replace('_', ' ', $failureReason)),
        };
    }

    /**
     * Extract browser name from user agent
     */
    private function getBrowserName(mixed $userAgent): string
    {
        if (! is_string($userAgent) || $userAgent === '') {
            return '-';
        }

        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        }

        if (str_contains($userAgent, 'Edg')) {
            return 'Edge';
        }

        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        }

        if (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        }

        if (str_contains($userAgent, 'Opera') || str_contains($userAgent, 'OPR')) {
            return 'Opera';
        }

        if (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident')) {
            return 'IE';
        }

        return 'Other';
    }

    /**
     * Build actions array
     */
    private function buildActions(LoginAttempt $loginAttempt): array
    {
        $actions = [];
        $isTrashed = $loginAttempt->trashed();

        // View action
        $actions['view'] = [
            'url' => route('app.logs.login-attempts.show', $loginAttempt->getKey()),
            'label' => 'View Details',
            'icon' => 'ri-eye-line',
            'class' => 'dropdown-item',
            'method' => 'GET',
        ];

        if ($isTrashed) {
            $actions['restore'] = [
                'url' => route('app.logs.login-attempts.restore', $loginAttempt->getKey()),
                'label' => 'Restore',
                'icon' => 'ri-refresh-line',
                'class' => 'dropdown-item text-success',
                'method' => 'PATCH',
                'confirm' => 'Restore this login attempt?',
            ];

            $actions['force_delete'] = [
                'url' => route('app.logs.login-attempts.force-delete', $loginAttempt->getKey()),
                'label' => 'Delete Permanently',
                'icon' => 'ri-delete-bin-fill',
                'class' => 'dropdown-item text-danger',
                'method' => 'DELETE',
                'confirm' => '⚠️ PERMANENT: This login attempt will be deleted forever and cannot be recovered.',
            ];
        } else {
            // Delete action
            $actions['delete'] = [
                'url' => route('app.logs.login-attempts.destroy', $loginAttempt->getKey()),
                'label' => 'Delete',
                'icon' => 'ri-delete-bin-line',
                'class' => 'dropdown-item text-danger',
                'method' => 'DELETE',
                'confirm' => 'Are you sure you want to delete this login attempt record?',
            ];
        }

        return $actions;
    }

    private function loginAttempt(): LoginAttempt
    {
        throw_unless($this->resource instanceof LoginAttempt, RuntimeException::class, 'LoginAttemptResource expects a LoginAttempt model instance.');

        return $this->resource;
    }
}

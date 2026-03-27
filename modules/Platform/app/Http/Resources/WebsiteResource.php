<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Carbon\Carbon;
use Modules\Platform\Definitions\WebsiteDefinition;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Server;

/**
 * WebsiteResource - JSON resource for DataGrid.
 *
 * @property int $id
 * @property string|null $type
 * @property string|WebsiteStatus|null $status
 * @property string|null $domain
 * @property string|null $astero_version
 * @property array<string, mixed>|null $customer_data
 * @property Carbon|null $expired_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server|null $server
 * @property-read Agency|null $agency
 */
class WebsiteResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new WebsiteDefinition;
    }

    protected function customFields(): array
    {
        $typeConfig = config('platform.website.types.'.$this->type, []);
        $typeColor = $typeConfig['color'] ?? 'secondary';

        $statusLabel = $this->status instanceof WebsiteStatus ? $this->status->label() : ucfirst((string) $this->status);
        $statusClass = $this->status instanceof WebsiteStatus ? $this->status->badgeClass() : 'bg-secondary-subtle text-secondary';

        $domain = $this->domain ?? '';
        $domainUrl = $this->primaryHostname() ? 'https://'.$this->primaryHostname() : null;

        $expiredOn = $this->expired_on;
        $daysUntilExpiry = $this->getDaysUntilExpiry();
        $isExpired = $expiredOn && $expiredOn->isPast();
        $expiryClass = 'text-muted';

        if ($isExpired) {
            $expiryClass = 'text-danger fw-medium';
        } elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 7) {
            $expiryClass = 'text-warning';
        } elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 30) {
            $expiryClass = 'text-info';
        }

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'domain' => $domain !== '' ? $domain : null,
            'domain_url' => $domainUrl,

            'astero_version' => $this->astero_version ?? '-',

            'customer_name' => $this->customer_data['name'] ?? $this->customer_data['email'] ?? '-',

            'server_name' => $this->server ? $this->server->name : '-',
            'server_name_class' => 'bg-light text-dark',

            'agency_name' => $this->agency ? $this->agency->name : '-',
            'agency_name_class' => 'bg-light text-dark',

            'type_label' => $typeConfig['label'] ?? ($this->type ? ucfirst((string) $this->type) : '-'),
            'type_class' => sprintf('bg-%s-subtle text-%s', $typeColor, $typeColor),

            'status_label' => $statusLabel,
            'status_class' => $statusClass,

            'expired_on' => $expiredOn ? app_date_time_format($expiredOn, 'date') : '-',
            'is_expired' => $isExpired,
            'days_until_expiry' => $daysUntilExpiry,
            'expired_on_class' => $expiryClass,

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),

            // DNS & CDN columns
            'dns_mode_label' => match ($this->dns_mode) {
                'managed' => 'Managed',
                'external' => 'External',
                default => 'Subdomain',
            },
            'dns_mode_class' => match ($this->dns_mode) {
                'managed' => 'bg-primary-subtle text-primary',
                'external' => 'bg-info-subtle text-info',
                default => 'bg-secondary-subtle text-secondary',
            },
            'cdn_status_label' => $this->pullzone_id ? 'Active' : '—',
            'cdn_status_class' => $this->pullzone_id ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary',
        ];
    }

    /**
     * Add website lifecycle actions (state-dependent).
     */
    protected function getActions(): array
    {
        $actions = parent::getActions();

        if ($this->resource->deleted_at !== null) {
            return $actions;
        }

        if ($this->can('edit_websites')) {
            if ($this->status === WebsiteStatus::Active) {
                $actions['suspend'] = [
                    'url' => route('platform.websites.update-status', [$this->id, WebsiteStatus::Suspended->value]),
                    'label' => 'Suspend',
                    'icon' => 'ri-pause-circle-line',
                    'method' => 'POST',
                    'confirm' => 'Are you sure you want to suspend this website?',
                ];
            } elseif ($this->status === WebsiteStatus::Suspended) {
                $actions['unsuspend'] = [
                    'url' => route('platform.websites.update-status', [$this->id, WebsiteStatus::Active->value]),
                    'label' => 'Unsuspend',
                    'icon' => 'ri-play-circle-line',
                    'method' => 'POST',
                    'confirm' => 'Are you sure you want to unsuspend this website?',
                ];
            }

            $actions['update_version'] = [
                'url' => route('platform.websites.update-version', $this->id),
                'label' => 'Update Version',
                'icon' => 'ri-refresh-line',
                'method' => 'POST',
                'confirm' => 'Update website to the latest version?',
            ];
        }

        return $actions;
    }

    private function getDaysUntilExpiry(): ?int
    {
        if (! $this->expired_on) {
            return null;
        }

        return (int) now()->diffInDays($this->expired_on, false);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Resources;

use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Agency\Definitions\WebsiteManageDefinition;
use Modules\Agency\Models\AgencyWebsite;

/** @mixin AgencyWebsite */
class WebsiteManageResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new WebsiteManageDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            // Core fields
            'name' => $this->name ?? 'Untitled',
            'domain' => $this->domain,
            'domain_url' => $this->domain_url,
            'site_id' => $this->site_id,

            // Business data
            'type' => $this->type ?? 'paid',
            'type_label' => $this->type_label,
            'type_class' => $this->type_class,

            'plan' => ucfirst($this->plan ?? 'N/A'),

            'customer_name' => $this->customer_name,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status_label,
            'status_class' => $this->status_class,

            // Infrastructure
            'server_name' => $this->server_name ?? 'N/A',
            'astero_version' => $this->astero_version ?? 'N/A',

            // Dates
            'expired_on' => $this->expired_on?->format('M d, Y'),
            'created_at' => $this->created_at?->format('M d, Y'),
            'updated_at' => $this->updated_at?->format('M d, Y'),

            // Owner
            'owner_name' => $this->owner_name
                ?? ($this->whenLoaded('owner', function (): ?string {
                    /** @var User|null $owner */
                    $owner = $this->owner;

                    return $owner ? trim($owner->first_name.' '.$owner->last_name) : null;
                }) ?: 'N/A'),
        ];
    }
}

<?php

namespace Modules\Platform\Http\Resources\Api\V1;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Website;

/**
 * API resource for website data returned to agency clients.
 *
 * Exposes only safe, non-sensitive fields. Never returns secret_key,
 * owner_password, or internal server credentials.
 *
 * @mixin Website
 */
class WebsiteApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_id' => $this->site_id,
            'site_id_prefix' => $this->site_id_prefix,
            'site_id_zero_padding' => $this->site_id_zero_padding,
            'domain' => $this->domain,
            'name' => $this->name,
            'type' => $this->type,
            'plan' => $this->plan_info ?? (object) [],
            'customer' => $this->customer_info ?? (object) [],
            'status' => $this->status instanceof BackedEnum ? $this->status->value : (string) $this->status,
            'status_label' => $this->status instanceof WebsiteStatus ? $this->status->label() : (string) $this->status,
            'is_www' => (bool) $this->is_www,
            'server_name' => $this->whenLoaded('server', fn () => $this->server?->name),
            'astero_version' => $this->astero_version,
            'admin_slug' => $this->admin_slug,
            'expired_on' => $this->expired_on?->toIso8601String(),
            'provisioned_at' => $this->when(
                $this->status?->value === 'active' || (is_string($this->status) && $this->status === 'active'),
                fn () => $this->updated_at?->toIso8601String()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

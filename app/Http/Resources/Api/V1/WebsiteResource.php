<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Website;
use RuntimeException;

class WebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $website = $this->website();
        $response_data = parent::toArray($request);

        $agencyRelation = $website->agency()->first();
        if ($request->boolean('with_agency') && $agencyRelation instanceof Agency) {
            $agencyobj = $agencyRelation;
            $ownerRelation = $agencyobj->owner()->first();
            $owner = $ownerRelation instanceof User ? $ownerRelation : null;
            $agency_data = [
                'id' => $agencyobj->id,
                'name' => $agencyobj->getAttribute('name'),
                'website' => $agencyobj->getAttribute('website'),
                'logo' => data_get($agencyobj, 'logoImage.media_url'),
                'icon' => data_get($agencyobj, 'iconImage.media_url'),
                'light_icon' => data_get($agencyobj, 'lightIconImage.media_url'),
                'favicon_icon' => data_get($agencyobj, 'faviconIconImage.media_url'),
                'apple_touch_icon' => data_get($agencyobj, 'appleTouchIconImage.media_url'),
                'android_device_icon' => data_get($agencyobj, 'androidDeviceIconImage.media_url'),
                'owner_first_name' => $owner?->first_name,
                'owner_last_name' => $owner?->last_name,
                'owner_email' => $owner?->email,
            ];

            // Look for agency admin credentials in secrets
            $secretRelation = $website->secrets()->where('key', 'agency_admin')->first();
            if ($secretRelation instanceof Secret) {
                $agency_data['agency_admin'] = [
                    'username' => $secretRelation->getMetadata('username'),
                    'password' => $secretRelation->getAttribute('decrypted_value'),
                ];
            }

            $response_data['agency'] = $agency_data;
        }

        $response_data['secrets'] = $this->whenLoaded('secrets');
        $response_data['storage_zones'] = $this->whenLoaded('storagezones');
        $response_data['active_storage_zone'] = $this->whenLoaded('activestoragezone');

        return $response_data;
    }

    private function website(): Website
    {
        throw_unless($this->resource instanceof Website, RuntimeException::class, 'WebsiteResource expects a Website model instance.');

        return $this->resource;
    }
}

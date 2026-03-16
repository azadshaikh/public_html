<?php

namespace Modules\CMS\Services;

/**
 * LocalSeoSchemaGenerator
 *
 * Generates JSON-LD structured data for local business SEO
 * Following schema.org LocalBusiness specifications
 */
class LocalSeoSchemaGenerator
{
    /**
     * Generate complete JSON-LD schema for local business
     */
    public function generate(array $settings): ?string
    {
        // Don't generate if schema is disabled
        if (! ($settings['seo_local_seo_is_schema'] ?? false)) {
            return null;
        }

        $schema = $this->buildBaseSchema($settings);

        if ($schema === []) {
            return null;
        }

        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate schema and wrap in script tag for HTML output
     */
    public function generateScriptTag(array $settings): string
    {
        $schema = $this->generate($settings);

        if (in_array($schema, [null, '', '0'], true)) {
            return '';
        }

        return '<script type="application/ld+json">'."\n".$schema."\n".'</script>';
    }

    /**
     * Build the base schema structure
     */
    protected function buildBaseSchema(array $settings): array
    {
        $type = $settings['seo_local_seo_type'] ?? 'Organization';
        $isPerson = $type === 'Person';

        // Base schema
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $isPerson ? 'Person' : $this->getBusinessType($settings),
        ];

        // Add business/person name
        if (! empty($settings['seo_local_seo_name'])) {
            $schema['name'] = $settings['seo_local_seo_name'];
        }

        // Add description
        if (! empty($settings['seo_local_seo_description'])) {
            $schema['description'] = $settings['seo_local_seo_description'];
        }

        // Add image/logo - Person only uses 'image', Organization uses both 'logo' and 'image'
        if (! empty($settings['seo_local_seo_logo_image'])) {
            $imageUrl = get_media_url($settings['seo_local_seo_logo_image']);
            $schema['image'] = $imageUrl;
            // Logo is only valid for Organization types
            if (! $isPerson) {
                $schema['logo'] = $imageUrl;
            }
        }

        // Add URL
        if (! empty($settings['seo_local_seo_url'])) {
            $schema['url'] = $settings['seo_local_seo_url'];
        }

        // Organization-only properties (not valid for Person)
        if (! $isPerson) {
            // Add founding date
            if (! empty($settings['seo_local_seo_founding_date'])) {
                $schema['foundingDate'] = $settings['seo_local_seo_founding_date'];
            }

            // Add price range
            if (! empty($settings['seo_local_seo_price_range'])) {
                $schema['priceRange'] = $settings['seo_local_seo_price_range'];
            }

            // Add currencies accepted
            if (! empty($settings['seo_local_seo_currencies_accepted'])) {
                $schema['currenciesAccepted'] = $settings['seo_local_seo_currencies_accepted'];
            }

            // Add payment accepted
            if (! empty($settings['seo_local_seo_payment_accepted'])) {
                $schema['paymentAccepted'] = $settings['seo_local_seo_payment_accepted'];
            }

            // Add geo coordinates (only for Organization/LocalBusiness)
            $geo = $this->buildGeoCoordinates($settings);
            if ($geo !== []) {
                $schema['geo'] = $geo;
            }

            // Add direct telephone and email for better Google visibility
            // (in addition to contactPoint)
            if (! empty($settings['seo_local_seo_phone'])) {
                $schema['telephone'] = $settings['seo_local_seo_phone'];
            }

            if (! empty($settings['seo_local_seo_email'])) {
                $schema['email'] = $settings['seo_local_seo_email'];
            }

            // Add contact point (only for Organization)
            $contactPoint = $this->buildContactPoint($settings);
            if ($contactPoint !== []) {
                $schema['contactPoint'] = $contactPoint;
            }

            // Add opening hours (only for Organization/LocalBusiness)
            $openingHours = $this->buildOpeningHours($settings);
            if ($openingHours !== []) {
                $schema['openingHoursSpecification'] = $openingHours;
            }
        }

        // Add address - valid for both Person and Organization
        $address = $this->buildAddress($settings);
        if ($address !== []) {
            $schema['address'] = $address;
        }

        // Person-specific: add telephone and email directly (not as contactPoint)
        if ($isPerson) {
            if (! empty($settings['seo_local_seo_phone'])) {
                $schema['telephone'] = $settings['seo_local_seo_phone'];
            }

            if (! empty($settings['seo_local_seo_email'])) {
                $schema['email'] = $settings['seo_local_seo_email'];
            }
        }

        // Add social media profiles - valid for both
        $sameAs = $this->buildSocialProfiles($settings);
        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }

        return $schema;
    }

    /**
     * Get the Schema.org business type
     *
     * Since the business_type value in settings is now stored as
     * the Schema.org type directly (e.g., 'Restaurant', 'Hotel'),
     * we just return it or default to 'LocalBusiness'.
     */
    protected function getBusinessType(array $settings): string
    {
        $businessType = $settings['seo_local_seo_business_type'] ?? '';

        return empty($businessType) ? 'LocalBusiness' : $businessType;
    }

    /**
     * Build address object
     */
    protected function buildAddress(array $settings): array
    {
        $address = [
            '@type' => 'PostalAddress',
        ];

        if (! empty($settings['seo_local_seo_street_address'])) {
            $address['streetAddress'] = $settings['seo_local_seo_street_address'];
        }

        if (! empty($settings['seo_local_seo_locality'])) {
            $address['addressLocality'] = $settings['seo_local_seo_locality'];
        }

        if (! empty($settings['seo_local_seo_region'])) {
            $address['addressRegion'] = $settings['seo_local_seo_region'];
        }

        if (! empty($settings['seo_local_seo_postal_code'])) {
            $address['postalCode'] = $settings['seo_local_seo_postal_code'];
        }

        if (! empty($settings['seo_local_seo_country_code'])) {
            $address['addressCountry'] = $settings['seo_local_seo_country_code'];
        }

        // Only return if at least one field is populated
        return count($address) > 1 ? $address : [];
    }

    /**
     * Build geo coordinates object
     */
    protected function buildGeoCoordinates(array $settings): array
    {
        $latitude = $settings['seo_local_seo_geo_coordinates_latitude'] ?? null;
        $longitude = $settings['seo_local_seo_geo_coordinates_longitude'] ?? null;

        if (empty($latitude) || empty($longitude)) {
            return [];
        }

        // Use floatval to preserve full decimal precision
        // The values are stored as strings, so we need to properly convert them
        return [
            '@type' => 'GeoCoordinates',
            'latitude' => floatval($latitude),
            'longitude' => floatval($longitude),
        ];
    }

    /**
     * Build contact point array
     */
    protected function buildContactPoint(array $settings): array
    {
        $contactPoints = [];

        // Main phone number
        if (! empty($settings['seo_local_seo_phone'])) {
            $contactPoints[] = [
                '@type' => 'ContactPoint',
                'telephone' => $settings['seo_local_seo_phone'],
                'contactType' => 'customer service',
            ];
        }

        // Additional phone numbers
        $phoneTypes = $settings['seo_local_seo_phone_number_type'] ?? [];
        $phoneNumbers = $settings['seo_local_seo_phone_number'] ?? [];

        if (is_array($phoneTypes) && is_array($phoneNumbers)) {
            foreach ($phoneTypes as $index => $type) {
                if (! empty($phoneNumbers[$index])) {
                    $contactPoints[] = [
                        '@type' => 'ContactPoint',
                        'telephone' => $phoneNumbers[$index],
                        'contactType' => str_replace('-', ' ', $type),
                    ];
                }
            }
        }

        // Email
        if (! empty($settings['seo_local_seo_email'])) {
            $contactPoints[] = [
                '@type' => 'ContactPoint',
                'email' => $settings['seo_local_seo_email'],
                'contactType' => 'customer service',
            ];
        }

        return count($contactPoints) === 1 ? $contactPoints[0] : (count($contactPoints) > 1 ? $contactPoints : []);
    }

    /**
     * Build opening hours specifications
     */
    protected function buildOpeningHours(array $settings): array
    {
        // Check if 24/7
        if (! empty($settings['seo_local_seo_is_opening_hour_24_7'])) {
            return [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    'opens' => '00:00',
                    'closes' => '23:59',
                ],
            ];
        }

        $days = $settings['seo_local_seo_opening_hour_day'] ?? [];
        $openingTimes = $settings['seo_local_seo_opening_hours'] ?? [];
        $closingTimes = $settings['seo_local_seo_closing_hours'] ?? [];

        // Decode JSON strings if needed (values are stored as JSON in the database)
        if (is_string($days)) {
            $days = json_decode($days, true) ?? [];
        }

        if (is_string($openingTimes)) {
            $openingTimes = json_decode($openingTimes, true) ?? [];
        }

        if (is_string($closingTimes)) {
            $closingTimes = json_decode($closingTimes, true) ?? [];
        }

        if (! is_array($days) || $days === []) {
            return [];
        }

        $specifications = [];

        foreach ($days as $index => $day) {
            if (empty($day)) {
                continue;
            }

            if (empty($openingTimes[$index])) {
                continue;
            }

            if (empty($closingTimes[$index])) {
                continue;
            }

            $specifications[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $day,
                'opens' => $openingTimes[$index],
                'closes' => $closingTimes[$index],
            ];
        }

        return $specifications;
    }

    /**
     * Build social media profiles array
     */
    protected function buildSocialProfiles(array $settings): array
    {
        $profiles = [];

        $socialFields = [
            'seo_local_seo_facebook_url',
            'seo_local_seo_twitter_url',
            'seo_local_seo_linkedin_url',
            'seo_local_seo_instagram_url',
            'seo_local_seo_youtube_url',
        ];

        foreach ($socialFields as $field) {
            if (! empty($settings[$field])) {
                $profiles[] = $settings[$field];
            }
        }

        return $profiles;
    }
}

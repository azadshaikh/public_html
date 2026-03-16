<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure your business information for rich search results and Google Knowledge Panel."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => 'Local SEO', 'active' => true]
        ]"
    />

    <form class="needs-validation" id="local-seo-form"
        action="{{ route('seo.settings.localseo.update') }}"
        method="POST" novalidate
        data-opening-days='@json($openingDaysArray ?? [])'>
        @csrf
        <input name="section" type="hidden" value="local-seo-settings-section">

        <x-alert-container containerId="seo-local-seo-alert-container" :showFlashMessages="false" :fieldLabels="[
            'is_schema' => 'Local SEO Schema',
            'type' => 'Entity Type',
            'business_type' => 'Business Type',
            'name' => 'Name',
            'description' => 'Description',
            'street_address' => 'Street Address',
            'locality' => 'City',
            'region' => 'State/Province',
            'postal_code' => 'Postal Code',
            'country_code' => 'Country',
            'phone' => 'Phone Number',
            'email' => 'Email Address',
            'logo_image' => 'Logo',
            'url' => 'Website URL',
            'is_opening_hour_24_7' => '24/7 Operation',
            'opening_hour_day' => 'Days',
            'opening_hours' => 'Opens',
            'closing_hours' => 'Closes',
            'price_range' => 'Price Range',
            'geo_coordinates_latitude' => 'Latitude',
            'geo_coordinates_longitude' => 'Longitude',
            'facebook_url' => 'Facebook',
            'twitter_url' => 'X (Twitter)',
            'linkedin_url' => 'LinkedIn',
            'instagram_url' => 'Instagram',
            'youtube_url' => 'YouTube',
            'founding_date' => 'Founded',
        ]" />

        <x-cms::seo-indexing-warning />

        <div class="row g-4">
            <!-- Left Column - Main Settings -->
            <div class="col-lg-8">
                <!-- Enable Schema Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-1">Enable Local Business Schema</h5>
                                <p class="text-muted mb-0 small">Add structured data to help search engines understand your business</p>
                            </div>
                            <x-form-elements.switch-input
                                id="is_schema"
                                name="is_schema"
                                :value="1"
                                ischecked="{{ ($settings_data['seo_local_seo_is_schema'] ?? old('is_schema', 'false')) === 'true' ? 1 : 0 }}"
                            />
                        </div>
                    </div>
                </div>

                <!-- Main Settings (shown when schema enabled) -->
                <div id="local-seo-fields" style="display: none;">
                    <!-- Basic Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="ri-building-line me-2 text-primary"></i>Basic Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Entity Type -->
                                <div class="col-md-6">
                                    <x-form-elements.select
                                        id="type"
                                        name="type"
                                        label="Entity Type"
                                        :options="json_encode([
                                            ['label' => 'Organization / Business', 'value' => 'Organization'],
                                            ['label' => 'Person / Individual', 'value' => 'Person'],
                                        ])"
                                        :value="$settings_data['seo_local_seo_type'] ?? 'Organization'"
                                    />
                                </div>

                                <!-- Business Type (only for Organization) -->
                                <div class="col-md-6 organization-field">
                                    <x-form-elements.select
                                        id="business_type"
                                        name="business_type"
                                        label="Business Category"
                                        placeholder="Select category..."
                                        :options="json_encode(config('cms.seo.business_types', []))"
                                        :value="$settings_data['seo_local_seo_business_type'] ?? 'LocalBusiness'"
                                        infotext="Schema.org business type"
                                    />
                                </div>

                                <!-- Name -->
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="name"
                                        name="name"
                                        label="Business / Person Name"
                                        placeholder="Enter your business or personal name"
                                        :value="$settings_data['seo_local_seo_name'] ?? ''"
                                        required
                                    />
                                </div>

                                <!-- Website URL -->
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="url"
                                        name="url"
                                        type="url"
                                        label="Website URL"
                                        placeholder="https://www.yourwebsite.com"
                                        :value="$settings_data['seo_local_seo_url'] ?? ''"
                                    />
                                </div>

                                <!-- Description -->
                                <div class="col-12">
                                    <x-form-elements.textarea
                                        id="description"
                                        name="description"
                                        label="Business Description"
                                        placeholder="Brief description of your business (shown in search results)"
                                        :value="$settings_data['seo_local_seo_description'] ?? ''"
                                        rows="3"
                                        infotext="Keep it under 160 characters for best results"
                                    />
                                </div>

                                <!-- Logo -->
                                <div class="col-12">
                                    <x-media-picker.image-field
                                        id="logo_image"
                                        name="logo_image"
                                        label="Logo"
                                        :value="$settings_data['seo_local_seo_logo_image'] ?? ''"
                                        :previewUrl="!empty($settings_data['seo_local_seo_logo_image']) ? get_media_url($settings_data['seo_local_seo_logo_image']) : null"
                                        infoText="Recommended: Square logo, minimum 112×112px"
                                        width="100px"
                                        height="100px"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="ri-phone-line me-2 text-primary"></i>Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        label="Phone Number"
                                        placeholder="+1 (555) 123-4567"
                                        :value="$settings_data['seo_local_seo_phone'] ?? ''"
                                        infotext="Include country code for international format"
                                    />
                                </div>
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="email"
                                        name="email"
                                        type="email"
                                        label="Email Address"
                                        placeholder="contact@yourbusiness.com"
                                        :value="$settings_data['seo_local_seo_email'] ?? ''"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="ri-map-pin-line me-2 text-primary"></i>Address
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-form-elements.input
                                        id="street_address"
                                        name="street_address"
                                        label="Street Address"
                                        placeholder="123 Main Street, Suite 100"
                                        :value="$settings_data['seo_local_seo_street_address'] ?? ''"
                                    />
                                </div>
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="locality"
                                        name="locality"
                                        label="City"
                                        placeholder="San Francisco"
                                        :value="$settings_data['seo_local_seo_locality'] ?? ''"
                                    />
                                </div>
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="region"
                                        name="region"
                                        label="State / Province"
                                        placeholder="California"
                                        :value="$settings_data['seo_local_seo_region'] ?? ''"
                                    />
                                </div>
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        id="postal_code"
                                        name="postal_code"
                                        label="Postal Code"
                                        placeholder="94105"
                                        :value="$settings_data['seo_local_seo_postal_code'] ?? ''"
                                    />
                                </div>
                                <div class="col-md-6">
                                    <x-form-elements.country-select
                                        id="country_code"
                                        name="country_code"
                                        label="Country"
                                        :value="$settings_data['seo_local_seo_country_code'] ?? ''"
                                    />
                                </div>

                                <!-- Geo Coordinates (Organization only) -->
                                <div class="col-12 organization-field">
                                    <hr class="my-2">
                                    <label class="form-label text-muted small mb-2">
                                        <i class="ri-map-2-line me-1"></i>GPS Coordinates (optional)
                                    </label>
                                </div>
                                <div class="col-md-6 organization-field">
                                    <x-form-elements.input
                                        id="geo_coordinates_latitude"
                                        name="geo_coordinates_latitude"
                                        label="Latitude"
                                        placeholder="37.7749"
                                        :value="$settings_data['seo_local_seo_geo_coordinates_latitude'] ?? ''"
                                    />
                                </div>
                                <div class="col-md-6 organization-field">
                                    <x-form-elements.input
                                        id="geo_coordinates_longitude"
                                        name="geo_coordinates_longitude"
                                        label="Longitude"
                                        placeholder="-122.4194"
                                        :value="$settings_data['seo_local_seo_geo_coordinates_longitude'] ?? ''"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Hours (Organization only) -->
                    <div class="card mb-4 organization-field">
                        <div class="card-header d-flex align-items-center justify-content-between" style="margin-top: -30px;">
                            <h5 class="card-title mb-0" style="margin-top: 15px;">
                                <i class="ri-time-line me-2 text-primary"></i>Business Hours
                            </h5>
                            <x-form-elements.switch-input
                                id="is_opening_hour_24_7"
                                name="is_opening_hour_24_7"
                                switchtext="Open 24/7"
                                :value="1"
                                ischecked="{{ ($settings_data['seo_local_seo_is_opening_hour_24_7'] ?? old('is_opening_hour_24_7', 'false')) === 'true' ? 1 : 0 }}"
                            />
                        </div>
                        <div class="card-body" id="hours-container">
                            @php
                                $seo_oh_days = [];
                                if (!empty($settings_data['seo_local_seo_opening_hour_day'])) {
                                    $seo_oh_days = is_string($settings_data['seo_local_seo_opening_hour_day'])
                                        ? json_decode($settings_data['seo_local_seo_opening_hour_day'], true)
                                        : $settings_data['seo_local_seo_opening_hour_day'];
                                }

                                $seo_oh_op_hours = [];
                                if (!empty($settings_data['seo_local_seo_opening_hours'])) {
                                    $seo_oh_op_hours = is_string($settings_data['seo_local_seo_opening_hours'])
                                        ? json_decode($settings_data['seo_local_seo_opening_hours'], true)
                                        : $settings_data['seo_local_seo_opening_hours'];
                                }

                                $seo_oh_cp_hours = [];
                                if (!empty($settings_data['seo_local_seo_closing_hours'])) {
                                    $seo_oh_cp_hours = is_string($settings_data['seo_local_seo_closing_hours'])
                                        ? json_decode($settings_data['seo_local_seo_closing_hours'], true)
                                        : $settings_data['seo_local_seo_closing_hours'];
                                }

                                $oh_counter = !empty($seo_oh_days) ? count($seo_oh_days) : 1;
                            @endphp

                            <div id="hours-list" data-opening-days='@json($openingDaysArray ?? [])'>
                                @for ($i = 0; $i < $oh_counter; $i++)
                                <div class="hour-row row g-2 mb-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Day</label>
                                        <select class="form-select form-select-sm" name="opening_hour_day[]">
                                            @foreach ($openingDaysArray ?? [] as $day)
                                            <option value="{{ $day }}" {{ isset($seo_oh_days[$i]) && $seo_oh_days[$i] === $day ? 'selected' : '' }}>{{ $day }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Opens</label>
                                        <input type="time" class="form-control form-control-sm" name="opening_hours[]" value="{{ $seo_oh_op_hours[$i] ?? '' }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Closes</label>
                                        <input type="time" class="form-control form-control-sm" name="closing_hours[]" value="{{ $seo_oh_cp_hours[$i] ?? '' }}">
                                    </div>
                                    <div class="col-md-2">
                                        @if ($i === 0)
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="addHourRow()">
                                            <i class="ri-add-line"></i>
                                        </button>
                                        @else
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeHourRow(this)">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                                @endfor
                            </div>
                            <p class="text-muted small mb-0 mt-2">
                                <i class="ri-information-line me-1"></i>Add multiple rows for different days or split shifts
                            </p>
                        </div>
                    </div>

                    <!-- Social Profiles -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="ri-share-line me-2 text-primary"></i>Social Profiles
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Link your social profiles for Knowledge Panel and sameAs schema</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-facebook-fill text-primary"></i></span>
                                        <input type="url" class="form-control" name="facebook_url" placeholder="https://facebook.com/yourpage"
                                            value="{{ $settings_data['seo_local_seo_facebook_url'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-twitter-x-fill"></i></span>
                                        <input type="url" class="form-control" name="twitter_url" placeholder="https://x.com/yourprofile"
                                            value="{{ $settings_data['seo_local_seo_twitter_url'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-linkedin-fill text-primary"></i></span>
                                        <input type="url" class="form-control" name="linkedin_url" placeholder="https://linkedin.com/company/yourcompany"
                                            value="{{ $settings_data['seo_local_seo_linkedin_url'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-instagram-fill text-danger"></i></span>
                                        <input type="url" class="form-control" name="instagram_url" placeholder="https://instagram.com/yourprofile"
                                            value="{{ $settings_data['seo_local_seo_instagram_url'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-youtube-fill text-danger"></i></span>
                                        <input type="url" class="form-control" name="youtube_url" placeholder="https://youtube.com/@yourchannel"
                                            value="{{ $settings_data['seo_local_seo_youtube_url'] ?? '' }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End of local-seo-fields -->
            </div>
            <!-- End of col-lg-8 -->

            <!-- Right Column - Quick Info & Preview -->
            <div class="col-lg-4">
                <div id="local-seo-sidebar" style="display: none;">
                    <!-- SEO Strength Score -->
                    <div class="card mb-4" id="seo-strength-card">
                        <div class="card-body text-center">
                            <div class="seo-score-ring-container mb-3">
                                <svg class="seo-score-ring" width="140" height="140" viewBox="0 0 140 140">
                                    <circle class="seo-score-ring-bg" cx="70" cy="70" r="60" fill="none" stroke="#e9ecef" stroke-width="10"/>
                                    <circle class="seo-score-ring-progress" cx="70" cy="70" r="60" fill="none" stroke="#dc3545" stroke-width="10" stroke-linecap="round" stroke-dasharray="377" stroke-dashoffset="377" transform="rotate(-90 70 70)"/>
                                </svg>
                                <div class="seo-score-content">
                                    <span class="seo-score-value" id="seo-score-value">0</span>
                                    <span class="seo-score-percent">%</span>
                                </div>
                            </div>
                            <div class="seo-score-grade mb-2">
                                <span class="badge fs-6" id="seo-score-badge">F</span>
                            </div>
                            <p class="seo-score-message small mb-3" id="seo-score-message">Start filling out your business information</p>

                            <!-- Section Progress -->
                            <div class="seo-sections-progress text-start">
                                <div class="section-item d-flex align-items-center justify-content-between py-2 border-bottom" data-section="basic">
                                    <span class="small"><i class="ri-building-line me-2 text-muted"></i>Basic Info</span>
                                    <span class="badge bg-light text-muted section-badge" id="section-badge-basic">0/5</span>
                                </div>
                                <div class="section-item d-flex align-items-center justify-content-between py-2 border-bottom" data-section="contact">
                                    <span class="small"><i class="ri-phone-line me-2 text-muted"></i>Contact</span>
                                    <span class="badge bg-light text-muted section-badge" id="section-badge-contact">0/2</span>
                                </div>
                                <div class="section-item d-flex align-items-center justify-content-between py-2 border-bottom" data-section="address">
                                    <span class="small"><i class="ri-map-pin-line me-2 text-muted"></i>Address</span>
                                    <span class="badge bg-light text-muted section-badge" id="section-badge-address">0/5</span>
                                </div>
                                <div class="section-item d-flex align-items-center justify-content-between py-2 border-bottom organization-field" data-section="hours">
                                    <span class="small"><i class="ri-time-line me-2 text-muted"></i>Hours</span>
                                    <span class="badge bg-light text-muted section-badge" id="section-badge-hours">0/1</span>
                                </div>
                                <div class="section-item d-flex align-items-center justify-content-between py-2" data-section="social">
                                    <span class="small"><i class="ri-share-line me-2 text-muted"></i>Social</span>
                                    <span class="badge bg-light text-muted section-badge" id="section-badge-social">0/5</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Settings (Organization only) -->
                    <div class="card mb-4 organization-field">
                        <div class="card-header">
                            <h6 class="card-title">Quick Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 organization-field">
                                <x-form-elements.select
                                    id="price_range"
                                    name="price_range"
                                    label="Price Range"
                                    :options="json_encode([
                                        ['label' => 'Not specified', 'value' => ''],
                                        ['label' => '$ - Budget', 'value' => '$'],
                                        ['label' => '$$ - Moderate', 'value' => '$$'],
                                        ['label' => '$$$ - Upscale', 'value' => '$$$'],
                                        ['label' => '$$$$ - Premium', 'value' => '$$$$'],
                                    ])"
                                    :value="$settings_data['seo_local_seo_price_range'] ?? ''"
                                />
                            </div>
                            <div class="mb-3 organization-field">
                                <x-form-elements.datepicker
                                    id="founding_date"
                                    name="founding_date"
                                    label="Founded Date"
                                    :value="$settings_data['seo_local_seo_founding_date'] ?? ''"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <button class="btn btn-primary w-100" type="submit">
                                <i class="ri-save-line me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>

                    <!-- Tips -->
                    <div class="card border-primary">
                        <div class="card-body">
                            <h6 class="text-primary mb-2"><i class="ri-lightbulb-line me-1"></i>Tips for Local SEO</h6>
                            <ul class="small text-muted mb-0 ps-3">
                                <li class="mb-1">Use your exact business name as it appears on Google</li>
                                <li class="mb-1">Include complete address with postal code</li>
                                <li class="mb-1">Add phone number with country code</li>
                                <li class="mb-1">Link all your official social profiles</li>
                                <li>Keep business hours up to date</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </form>

    <x-media-picker.media-modal />

    <style>
        .seo-score-ring-container {
            position: relative;
            display: inline-block;
        }
        .seo-score-ring-progress {
            transition: stroke-dashoffset 0.6s ease, stroke 0.3s ease;
        }
        .seo-score-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .seo-score-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
        }
        .seo-score-percent {
            font-size: 1rem;
            color: #6c757d;
        }
        .seo-score-grade .badge {
            padding: 0.5em 1em;
        }
        .section-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
        .section-badge {
            min-width: 45px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        /* Score color classes */
        .seo-score-f { color: #dc3545; }
        .seo-score-d { color: #fd7e14; }
        .seo-score-c { color: #ffc107; }
        .seo-score-b { color: #20c997; }
        .seo-score-a { color: #198754; }
    </style>

    <x-script-loader :wrap="false" :scripts="['modules/CMS/resources/views/seo/settings/js/local-seo-settings.js']" />
</x-app-layout>

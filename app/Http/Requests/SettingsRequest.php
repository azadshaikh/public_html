<?php

namespace App\Http\Requests;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // use url parameter meta_group for set rules based on meta_group
        $meta_group = $this->route('meta_group') ?? $this->input('meta_group');
        $rules = [];
        switch ($meta_group) {
            case 'general':
                $rules = [
                    'site_title' => ['required', 'string', 'max:255'],
                    'tagline' => ['nullable', 'string', 'max:255'],
                    'app_title' => ['nullable', 'string', 'max:255'],
                    'site_logo' => ['nullable'],
                    'favicon' => ['nullable'],
                    'container_max_width' => ['nullable', 'integer', 'min:1'],
                    'sidebar_width' => ['nullable', 'integer', 'min:1'],
                    'sidebar_mini_width' => ['nullable', 'integer', 'min:1'],
                    'topbar_height' => ['nullable', 'integer', 'min:1'],
                    'footer_height' => ['nullable', 'integer', 'min:1'],
                ];
                break;
            case 'colors':
                $rules = [
                    'primary_color' => ['nullable', 'string', 'max:255'],
                    'secondary_color' => ['nullable', 'string', 'max:255'],
                ];
                break;
            case 'storage':
                $storageDriver = $this->input('storage_driver');

                $rules = [
                    'storage_driver' => ['required', 'string'],
                    'root_folder' => ['required', 'string', 'max:255'],
                    'max_storage_size' => ['required', 'integer', 'min:1'],
                    'storage_cdn_url' => ['nullable', 'string', 'max:255'],
                    'use_path_style_endpoint' => ['nullable', 'boolean'],
                    'ftp_root' => ['nullable', 'string', 'max:255'],
                    'ftp_passive' => ['nullable', 'boolean'],
                    'ftp_ssl' => ['nullable', 'boolean'],
                ];

                // Add S3 validation rules only if S3 is selected
                if ($storageDriver === 's3') {
                    $rules = array_merge($rules, [
                        'access_key' => ['required', 'string', 'max:255'],
                        'secret_key' => ['required', 'string', 'max:255'],
                        'bucket' => ['required', 'string', 'max:255'],
                        'region' => ['required', 'string', 'max:255'],
                        'endpoint' => ['required', 'string', 'max:255'],
                    ]);
                }

                // Add FTP validation rules only if FTP is selected
                if ($storageDriver === 'ftp') {
                    $rules = array_merge($rules, [
                        'ftp_host' => ['required', 'string', 'max:255'],
                        'ftp_username' => ['required', 'string', 'max:255'],
                        'ftp_password' => ['required', 'string', 'max:255'],
                        'ftp_port' => ['required', 'integer', 'min:1', 'max:65535'],
                        'ftp_timeout' => ['required', 'integer', 'min:1'],
                        'ftp_ssl_mode' => ['required', 'string'],
                    ]);
                }

                break;
            case 'business':
                $rules = [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'contact_number' => ['required', 'string', 'max:255'],
                    'address_1' => ['required', 'string', 'max:255'],
                    'address_2' => ['nullable', 'string', 'max:255'],
                    'city' => ['nullable', 'string', 'max:255'],
                    'state' => ['nullable', 'string', 'max:255'],
                    'country' => ['nullable', 'string', 'max:255'],
                    'zip_code' => ['nullable', 'string', 'max:255'],
                    'website' => ['nullable', 'string', 'max:255'],
                    'tax_number' => ['nullable', 'string', 'max:255'],
                ];
                break;
            case 'login_security':
                $rules = [
                    'admin_login_url_slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii'],
                    'limit_login_attempts_enabled' => ['nullable', 'boolean'],
                    'limit_login_attempts' => ['required_if:limit_login_attempts_enabled,true', 'integer', 'min:1'],
                    'lockout_time' => ['required_if:limit_login_attempts_enabled,true', 'integer', 'min:1'],
                ];
                break;
            case 'social_authentication':
                $rules = [
                    'enable_social_authentication' => ['nullable'],
                    'google_client_id' => ['required_if:enable_google_authentication,1'],
                    'google_client_secret' => ['required_if:enable_google_authentication,1'],
                    'github_client_id' => ['required_if:enable_github_authentication,1'],
                    'github_client_secret' => ['required_if:enable_github_authentication,1'],
                ];
                break;
            case 'email':
                $rules = [
                    'driver' => ['required'],
                    'sent_from_name' => ['required', 'string', 'max:255'],
                    'sent_from_address' => ['required', 'email', 'max:255'],
                    'smtp_host' => ['required_if:driver,smtp'],
                    'smtp_port' => ['required_if:driver,smtp'],
                    'smtp_security_type' => ['required_if:driver,smtp'],
                    'smtp_username' => ['required_if:driver,smtp'],
                    'smtp_password' => ['required_if:driver,smtp'],
                ];
                break;
            case 'localization':
                $rules = [
                    'language' => ['required'],
                    'date_format' => ['required'],
                    'time_format' => ['required'],
                    'timezone' => ['required'],
                ];
                break;
            case 'site_access_protection':
                $rules = [
                    'mode_enabled' => ['nullable', 'boolean'],
                    'password' => ['required_if:mode_enabled,true', 'string', 'max:255'],
                    'protection_message' => ['required_if:mode_enabled,true', 'string'],
                ];
                break;
                // Google AdSense validation moved to CMS module
                // See: modules/CMS/app/Http/Requests/UpdateSeoSettingsRequest.php
            case 'maintenance':
                $rules = [
                    'mode_enabled' => ['nullable', 'boolean'],
                    'maintenance_mode_type' => ['required_if:mode_enabled,true', 'in:frontend,both'],
                    'title' => ['nullable', 'string', 'max:255'],
                    'message' => ['nullable', 'string'],
                ];
                break;
            case 'coming_soon':
                $rules = [
                    'enabled' => ['nullable', 'boolean'],
                    'description' => ['nullable', 'string'],
                ];
                break;
            case 'media':
                $rules = [
                    'max_file_name_length' => ['required', 'integer', 'min:1', 'max:255'],
                    'image_quality' => ['required', 'integer', 'min:1', 'max:100'],
                    'max_upload_size' => ['required', 'integer', 'min:1'],
                    'allowed_file_types' => ['nullable', 'string'],
                    'image_optimization' => ['nullable', 'boolean'],
                    'delete_trashed' => ['nullable', 'boolean'],
                    'delete_trashed_days' => ['nullable', 'integer', 'min:1'],
                    'thumbnail_width' => ['nullable', 'integer', 'min:1'],
                    'small_width' => ['nullable', 'integer', 'min:1'],
                    'medium_width' => ['nullable', 'integer', 'min:1'],
                    'large_width' => ['nullable', 'integer', 'min:1'],
                    'xlarge_width' => ['nullable', 'integer', 'min:1'],
                ];
                break;
            case 'registration':
                $rules = [
                    'enable_registration' => ['nullable', 'boolean'],
                    'default_role' => [
                        'required',
                        'integer',
                        Rule::exists('roles', 'id')->where(fn ($query) => $query->where('status', Status::ACTIVE->value)),
                    ],
                    'require_email_verification' => ['nullable', 'boolean'],
                    'auto_approve' => ['nullable', 'boolean'],
                ];
                break;
            case 'branding':
                $rules = [
                    'brand_name' => ['required', 'string', 'max:255'],
                    'brand_website' => ['required', 'string', 'max:255'],
                    'logo' => ['required'],
                    'icon' => ['nullable'],
                    'favicon' => ['nullable'],
                    'apple_touch_icon' => ['nullable'],
                    'android_icon' => ['nullable'],
                    'theme_mode' => ['nullable', 'string'],
                    'primary_color' => ['nullable', 'string', 'max:255'],
                    'secondary_color' => ['nullable', 'string', 'max:255'],
                    'primary_color_rgb' => ['nullable', 'string', 'max:255'],
                    'secondary_color_rgb' => ['nullable', 'string', 'max:255'],
                ];
                break;
            case 'debug':
                $rules = [
                    'enable_debugging' => ['nullable', 'boolean'],
                    'enable_debugging_bar' => ['nullable', 'boolean'],
                    'remove_xframe_header' => ['nullable', 'boolean'],
                ];
                break;
            case 'app':
                $rules = [
                    'homepage_redirect_enabled' => ['nullable', 'boolean'],
                    'homepage_redirect_slug' => ['nullable', 'string', 'max:255'],
                ];
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [];
        $metaGroup = $this->route('meta_group') ?? $this->input('meta_group');
        switch ($metaGroup) {
            case 'general':
                $messages = [
                    'app_title.required' => 'App title is required',
                    'site_logo.image' => 'Site logo must be an image',
                    'site_logo.mimes' => 'Site logo must be a valid image format',
                    'site_logo.max' => 'Site logo must be less than 2MB',
                    'favicon.image' => 'Favicon must be an image',
                    'favicon.mimes' => 'Favicon must be a valid image format',
                    'favicon.max' => 'Favicon must be less than 2MB',
                    'container_max_width.required' => 'Container max width is required',
                    'container_max_width.integer' => 'Container max width must be an integer',
                    'container_max_width.min' => 'Container max width must be at least 1',
                    'sidebar_width.required' => 'Sidebar width is required',
                    'sidebar_width.integer' => 'Sidebar width must be an integer',
                    'sidebar_width.min' => 'Sidebar width must be at least 1',
                    'sidebar_mini_width.required' => 'Sidebar mini width is required',
                    'sidebar_mini_width.integer' => 'Sidebar mini width must be an integer',
                    'sidebar_mini_width.min' => 'Sidebar mini width must be at least 1',
                    'topbar_height.required' => 'Topbar height is required',
                    'topbar_height.integer' => 'Topbar height must be an integer',
                    'topbar_height.min' => 'Topbar height must be at least 1',
                    'footer_height.required' => 'Footer height is required',
                    'footer_height.integer' => 'Footer height must be an integer',
                    'footer_height.min' => 'Footer height must be at least 1',
                ];
                break;
            case 'colors':
                $messages = [
                    'primary_color.required' => 'Primary color is required',
                    'secondary_color.required' => 'Secondary color is required',
                ];
                break;
            case 'business':
                $messages = [
                    'business_name.required' => 'Business name is required',
                    'business_email.required' => 'Business email is required',
                    'business_email.email' => 'Business email is invalid',
                    'business_contact_number.required' => 'Business contact number is required',
                    'business_address_1.required' => 'Business address is required',
                ];
                break;
            case 'storage':
                $storageDriver = $this->input('storage_driver');

                $messages = [
                    'storage_driver.required' => 'Storage driver is required',
                    'storage_driver.string' => 'Storage driver must be a string',
                    'root_folder.required' => 'Root folder is required',
                    'root_folder.string' => 'Root folder must be a string',
                    'root_folder.max' => 'Root folder must be less than 255 characters',
                    'max_storage_size.required' => 'Max storage size is required',
                    'max_storage_size.integer' => 'Max storage size must be an integer',
                    'max_storage_size.min' => 'Max storage size must be at least 1',
                    'storage_cdn_url.string' => 'Storage CDN URL must be a string',
                    'storage_cdn_url.max' => 'Storage CDN URL must be less than 255 characters',
                ];

                // Add S3 validation messages only if S3 is selected
                if ($storageDriver === 's3') {
                    $messages = array_merge($messages, [
                        'access_key.required' => 'Access key is required',
                        'access_key.string' => 'Access key must be a string',
                        'access_key.max' => 'Access key must be less than 255 characters',
                        'secret_key.required' => 'Secret key is required',
                        'secret_key.string' => 'Secret key must be a string',
                        'secret_key.max' => 'Secret key must be less than 255 characters',
                        'bucket.required' => 'Bucket name is required',
                        'bucket.string' => 'Bucket name must be a string',
                        'bucket.max' => 'Bucket name must be less than 255 characters',
                        'region.required' => 'Region is required',
                        'region.string' => 'Region must be a string',
                        'region.max' => 'Region must be less than 255 characters',
                        'endpoint.required' => 'Endpoint is required',
                        'endpoint.string' => 'Endpoint must be a string',
                        'endpoint.max' => 'Endpoint must be less than 255 characters',
                    ]);
                }

                // Add FTP validation messages only if FTP is selected
                if ($storageDriver === 'ftp') {
                    $messages = array_merge($messages, [
                        'ftp_host.required' => 'FTP host is required',
                        'ftp_host.string' => 'FTP host must be a string',
                        'ftp_host.max' => 'FTP host must be less than 255 characters',
                        'ftp_username.required' => 'FTP username is required',
                        'ftp_username.string' => 'FTP username must be a string',
                        'ftp_username.max' => 'FTP username must be less than 255 characters',
                        'ftp_password.required' => 'FTP password is required',
                        'ftp_password.string' => 'FTP password must be a string',
                        'ftp_password.max' => 'FTP password must be less than 255 characters',
                        'ftp_root.string' => 'FTP root must be a string',
                        'ftp_root.max' => 'FTP root must be less than 255 characters',
                        'ftp_port.required' => 'FTP port is required',
                        'ftp_port.integer' => 'FTP port must be an integer',
                        'ftp_port.min' => 'FTP port must be at least 1',
                        'ftp_port.max' => 'FTP port must be at most 65535',
                        'ftp_timeout.required' => 'FTP timeout is required',
                        'ftp_timeout.integer' => 'FTP timeout must be an integer',
                        'ftp_timeout.min' => 'FTP timeout must be at least 1',
                        'ftp_ssl_mode.required' => 'FTP SSL mode is required',
                        'ftp_ssl_mode.string' => 'FTP SSL mode must be a string',
                    ]);
                }

                break;
            case 'login_security':
                $messages = [
                    'admin_login_url_slug.required' => 'Admin login URL slug is required',
                    'admin_login_url_slug.alpha_dash' => 'Admin login URL slug may only contain letters, numbers, dashes, and underscores',
                    'limit_login_attempts.required_if' => 'Login attempts limit is required',
                    'limit_login_attempts.integer' => 'Login attempts limit must be an integer',
                    'limit_login_attempts.min' => 'Login attempts limit must be at least 1',
                    'lockout_time.required_if' => 'Lockout time is required',
                    'lockout_time.integer' => 'Lockout time must be an integer',
                    'lockout_time.min' => 'Lockout time must be at least 1',
                ];

                break;
            case 'social_authentication':
                $messages = [
                    'google_client_id.required_if' => 'Google client ID is required',
                    'google_client_secret.required_if' => 'Google client secret is required',
                    'github_client_id.required_if' => 'GitHub client ID is required',
                    'github_client_secret.required_if' => 'GitHub client secret is required',
                ];
                break;
            case 'email':
                $messages = [
                    'email_driver.required' => 'Mail Sending Method is required',
                    'email_sent_from_name.required' => 'Sender Name is required',
                    'email_sent_from_address.required' => 'Sender Email Address is required',
                    'smtp_host.required_if' => 'SMTP Server Host is required',
                    'smtp_port.required_if' => 'SMTP Port Number is required',
                    'smtp_security_type.required_if' => 'Encryption Protocol is required',
                    'smtp_username.required_if' => 'SMTP Username is required',
                    'smtp_password.required_if' => 'SMTP Password is required',
                ];
                break;
            case 'localization':
                $messages = [
                    'language.required' => 'Site Language is required',
                    'date_format.required' => 'Date format is required',
                    'time_format.required' => 'Time format is required',
                    'timezone.required' => 'Timezone is required',
                ];
                break;
            case 'site_access_protection':
                $messages = [
                    'password.required_if' => 'Password is required',
                    'message.required_if' => 'Message is required',
                ];
                break;
                // Google AdSense messages moved to CMS module
            case 'maintenance':
                $messages = [
                    'maintenance_message.required_if' => 'Maintenance message is required',
                ];
                break;
            case 'registration':
                $messages = [
                    'default_role.required' => 'Default role is required',
                    'default_role.integer' => 'Default role must be a valid role.',
                    'default_role.exists' => 'Selected default role is not available.',
                ];
                break;
            case 'media':
                $messages = [
                    'max_file_name_length.required' => 'Max file name length is required',
                    'max_file_name_length.integer' => 'Max file name length must be an integer',
                    'max_file_name_length.min' => 'Max file name length must be at least 1',
                    'max_file_name_length.max' => 'Max file name length must be at most 255',
                    'image_quality.required' => 'Image quality is required',
                    'image_quality.integer' => 'Image quality must be an integer',
                    'image_quality.min' => 'Image quality must be at least 1',
                    'image_quality.max' => 'Image quality must be at most 100',
                    'max_upload_size.required' => 'Max upload size is required',
                    'max_upload_size.integer' => 'Max upload size must be an integer',
                    'max_upload_size.min' => 'Max upload size must be at least 1',
                    'allowed_file_types.string' => 'Allowed file types must be a string',
                    'delete_trashed_days.integer' => 'Delete trashed days must be an integer',
                    'delete_trashed_days.min' => 'Delete trashed days must be at least 1',
                    'small_width.integer' => 'Small width must be an integer',
                    'small_width.min' => 'Small width must be at least 1',
                    'small_height.integer' => 'Small height must be an integer',
                    'small_height.min' => 'Small height must be at least 1',
                    'medium_width.integer' => 'Medium width must be an integer',
                    'medium_width.min' => 'Medium width must be at least 1',
                    'medium_height.integer' => 'Medium height must be an integer',
                    'medium_height.min' => 'Medium height must be at least 1',
                    'large_width.integer' => 'Large width must be an integer',
                    'large_width.min' => 'Large width must be at least 1',
                    'large_height.integer' => 'Large height must be an integer',
                    'large_height.min' => 'Large height must be at least 1',
                ];
                break;
            case 'branding':
                $messages = [
                    'brand_name.required' => 'Brand name is required',
                    'brand_name.string' => 'Brand name must be a string',
                    'brand_name.max' => 'Brand name must be less than 255 characters',
                    'brand_website.required' => 'Brand website is required',
                    'brand_website.string' => 'Brand website must be a string',
                    'brand_website.max' => 'Brand website must be less than 255 characters',
                    'logo.required' => 'Logo is required',
                    'theme_mode.string' => 'Theme mode must be a string',
                    'primary_color.string' => 'Primary color must be a string',
                    'primary_color.max' => 'Primary color must be less than 255 characters',
                    'secondary_color.string' => 'Secondary color must be a string',
                    'secondary_color.max' => 'Secondary color must be less than 255 characters',
                    'primary_color_rgb.string' => 'Primary color RGB must be a string',
                    'primary_color_rgb.max' => 'Primary color RGB must be less than 255 characters',
                    'secondary_color_rgb.string' => 'Secondary color RGB must be a string',
                    'secondary_color_rgb.max' => 'Secondary color RGB must be less than 255 characters',
                ];
                break;
            case 'app':
                $messages = [
                    'homepage_redirect_slug.string' => 'Redirect URL Slug must be a string',
                    'homepage_redirect_slug.max' => 'Redirect URL Slug must be less than 255 characters',
                ];
                break;
        }

        return $messages;
    }
}

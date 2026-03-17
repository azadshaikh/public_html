# ASTERO BUILDER - WEBSITE ENVIRONMENT CONFIGURATION
# Auto-generated for deployed websites
# Template variables are replaced with actual values during deployment

@php
    $isPgsqlConnection = ($db_connection ?? 'pgsql') === 'pgsql';
    $resolvedDbName = $isPgsqlConnection ? strtolower((string) ($database_name ?? '')) : ($database_name ?? '');
    $resolvedDbUsername = $isPgsqlConnection ? strtolower((string) ($database_username ?? '')) : ($database_username ?? '');
@endphp

# Application
APP_NAME="{{ $app_name }}"
APP_ENV=production
APP_KEY=
APP_TIMEZONE=UTC
APP_URL="{{ $app_url }}"

# Admin Panel
ADMIN_SLUG={{ $admin_slug ?? 'admin' }}

# Astero Platform
AGENCY_ID="{{ $agency_uid }}"
WEBSITE_ID={{ $website_id ?? '' }}
WEBSITE_PLAN={{ $website_plan ?? '' }}
WS_SECRETKEY="{{ $secret_key }}"
@if(!empty($agency_secret_key))
AGENCY_PLAN={{ $agency_plan ?? '' }}
AGY_SECRETKEY="{{ $agency_secret_key }}"
@endif

# Branding
BRANDING_NAME={{ $branding_name ?? '' }}
BRANDING_WEBSITE={{ $branding_website ?? '' }}
BRANDING_LOGO={{ $branding_logo ?? '' }}
BRANDING_ICON={{ $branding_icon ?? '' }}

# Localization
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

# Frontend HTML minification for website responses
HTML_MINIFICATION_ENABLED=true

# CDN Cache Headers (Sets Cache-Control headers for CDN/browser caching)
CDN_CACHE_HEADERS=true
CDN_CACHE_MAX_AGE=31536000

# Debugging (disabled for production)
APP_DEBUG=false
DEBUGBAR_ENABLED=false

APP_AUTO_UPDATE=false

# Logging
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database
DB_CONNECTION={{ $db_connection ?? 'pgsql' }}
DB_HOST=127.0.0.1
DB_PORT={{ $db_port ?? '5432' }}
DB_DATABASE={{ $resolvedDbName }}
DB_USERNAME={{ $resolvedDbUsername }}
DB_PASSWORD={{ $database_password }}
DB_TIMEOUT=300
@if(($db_connection ?? 'pgsql') === 'pgsql')
DB_CHARSET=utf8
@else
DB_CHARSET={{ $db_charset ?? 'utf8mb4' }}
DB_COLLATION={{ $db_collation ?? 'utf8mb4_unicode_ci' }}
@endif

# Session
SESSION_DRIVER=database
SESSION_CONNECTION=
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_COOKIE={{ $website_id ?? 'laravel' }}_session

# Broadcasting & Queues
BROADCAST_CONNECTION=log
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database
CACHE_PREFIX=

# Rate Limiting
CACHE_LIMITER=database

# Mail
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@{{ $domain }}"
MAIL_FROM_NAME="{{ $app_name }}"

# Filesystem & Storage
FILESYSTEM_DISK=public
STORAGE_DISK=public
MAX_STORAGE_SIZE=1
STORAGE_ROOT_FOLDER={{ $media_slug ?? 'media' }}
STORAGE_CDN_URL=

# FTP Storage
FTP_HOST=
FTP_USERNAME=
FTP_PASSWORD=
FTP_ROOT=
FTP_PORT=21
FTP_PASSIVE=true
FTP_TIMEOUT=30
FTP_SSL=true
FTP_SSL_MODE=explicit

# AWS S3 Storage
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Media Management
MEDIA_MAX_SIZE_IN_MB=20
MEDIA_MAX_FILE_NAME_LENGTH=100
MEDIA_ALLOWED_FILE_TYPES="image/png,image/jpg,image/jpeg,image/gif,image/webp,image/svg+xml,image/x-icon,image/bmp,video/mp4,video/webm,video/x-webm,video/avi,video/mov,video/wmv,video/x-matroska,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv"

# Image Processing
MEDIA_IMAGE_OPTIMIZATION=true
MEDIA_IMAGE_QUALITY=70
MEDIA_THUMBNAIL_WIDTH=300
MEDIA_SMALL_WIDTH=500
MEDIA_MEDIUM_WIDTH=700
MEDIA_LARGE_WIDTH=1200
MEDIA_XLARGE_WIDTH=1920

# Media Cleanup
MEDIA_AUTO_DELETE_TRASHED=true
MEDIA_TRASH_AUTO_DELETE_DAYS=7

# Frontend Build
VITE_APP_NAME="{{ $app_name }}"

# Social Authentication
SOCIAL_AUTH_ENABLED=false
GOOGLE_AUTH_ENABLED=false
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT=
GITHUB_AUTH_ENABLED=false
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT=

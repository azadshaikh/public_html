<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Media File Types
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of allowed MIME types for media uploads.
    | You can override this in your .env file using MEDIA_ALLOWED_FILE_TYPES.
    | If not set, only common image types (excluding SVG) are allowed:
    |   image/png, image/jpg, image/jpeg, image/gif, image/webp, image/x-icon, image/bmp
    |
    | Example:
    | MEDIA_ALLOWED_FILE_TYPES="image/png,image/jpg,video/mp4,application/pdf"
    */
    'media_allowed_file_types' => env(
        'MEDIA_ALLOWED_FILE_TYPES',
        'image/png,image/jpg,image/jpeg,image/gif,image/webp,image/x-icon,image/bmp'
    ),

    /*
    |--------------------------------------------------------------------------
    | Media Storage Root
    |--------------------------------------------------------------------------
    |
    | The root folder for all media storage, used as a prefix for all media paths.
    | You can override this in your .env file using STORAGE_ROOT_FOLDER.
    |
    */
    'media_storage_root' => env('STORAGE_ROOT_FOLDER', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Storage Size
    |--------------------------------------------------------------------------
    |
    | Maximum storage size in GB for media uploads. Set to -1 for unlimited.
    | You can override this in your .env file using MAX_STORAGE_SIZE.
    |
    */
    'max_storage_size' => env('MAX_STORAGE_SIZE', 1),

    /*
    |--------------------------------------------------------------------------
    | Trash Auto-Delete Days
    |--------------------------------------------------------------------------
    |
    | Number of days after which trashed media files are permanently deleted.
    | Set to -1 to disable auto-deletion of trashed media files.
    | You can override this in your .env file using MEDIA_TRASH_AUTO_DELETE_DAYS.
    | Default: 30
    */
    'trash_auto_delete_days' => env('MEDIA_TRASH_AUTO_DELETE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Maximum Media File Name Length
    |--------------------------------------------------------------------------
    |
    | The maximum allowed length (in characters) for uploaded media file names.
    | You can override this in your .env file using MEDIA_MAX_FILE_NAME_LENGTH.
    | Default: 100
    */
    'max_file_name_length' => env('MEDIA_MAX_FILE_NAME_LENGTH', 100),

    /*
    |--------------------------------------------------------------------------
    | Image Quality Settings
    |--------------------------------------------------------------------------
    |
    | Quality settings for different image formats during conversion.
    | Values should be between 1-100, where 100 is the highest quality.
    | You can override these in your .env file.
    */
    'image_quality' => env('MEDIA_IMAGE_QUALITY', 80),
    'webp_quality' => env('MEDIA_WEBP_QUALITY', 80),
    'jpeg_quality' => env('MEDIA_JPEG_QUALITY', 85),
    'png_quality' => env('MEDIA_PNG_QUALITY', 90),

    /*
    |--------------------------------------------------------------------------
    | Responsive Image Size Customization
    |--------------------------------------------------------------------------
    |
    | Width values for responsive image breakpoints.
    | These can override the defaults in MediaVariationService.
    | You can override these in your .env file.
    */
    'thumbnail_width' => env('MEDIA_THUMBNAIL_WIDTH', 150),
    'small_width' => env('MEDIA_SMALL_WIDTH', 400),
    'medium_width' => env('MEDIA_MEDIUM_WIDTH', 800),
    'large_width' => env('MEDIA_LARGE_WIDTH', 1200),
    'xlarge_width' => env('MEDIA_XLARGE_WIDTH', 1920),

    /*
    |--------------------------------------------------------------------------
    | Image Conversions Toggle
    |--------------------------------------------------------------------------
    |
    | When enabled, uploaded images are automatically converted to WebP,
    | thumbnails are generated, and responsive sizes are created.
    | When disabled, only the original file is stored.
    */
    'image_conversions_enabled' => (bool) env('MEDIA_IMAGE_OPTIMIZATION', false),

    /*
    |--------------------------------------------------------------------------
    | Processing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for media processing behavior.
    */
    'queue_threshold_mb' => env('MEDIA_QUEUE_THRESHOLD_MB', 3),
    'small_image_threshold' => env('MEDIA_SMALL_IMAGE_THRESHOLD', 400),
];

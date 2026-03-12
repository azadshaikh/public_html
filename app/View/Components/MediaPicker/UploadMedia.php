<?php

namespace App\View\Components\MediaPicker;

use Illuminate\View\Component;
use Illuminate\View\View;

class UploadMedia extends Component
{
    public $upload_settings = [];

    public function __construct(public $ismodal = false) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $this->upload_settings = null;
        $max_upload_size = config('media-library.max_file_size') / (1024 * 1024);
        $accepted_file_types = config('media.media_allowed_file_types');

        $allowed_types = explode(',', $accepted_file_types);

        // Categorize file types for simpler display
        $has_images = false;
        $has_videos = false;
        $has_documents = false;

        foreach ($allowed_types as $type) {
            $type = trim($type);
            if (str_starts_with($type, 'image/')) {
                $has_images = true;
            } elseif (str_starts_with($type, 'video/')) {
                $has_videos = true;
            } elseif (str_starts_with($type, 'application/') || str_starts_with($type, 'text/')) {
                $has_documents = true;
            }
        }

        $friendly_categories = [];
        if ($has_images) {
            $friendly_categories[] = 'Images';
        }

        if ($has_videos) {
            $friendly_categories[] = 'Videos';
        }

        if ($has_documents) {
            $friendly_categories[] = 'Documents';
        }

        $this->upload_settings = [
            'max_size_mb' => $max_upload_size,
            'max_size_bytes' => config('media-library.max_file_size'),
            'accepted_mime_types' => $accepted_file_types,
            'friendly_file_types' => implode(', ', $friendly_categories),
            'max_filename_length' => (int) config('media.max_file_name_length', 100),
            'upload_route' => route('app.media.upload-media'),
            'settings_route' => route('app.media.upload-settings'),
        ];

        return view('components.media-picker.upload-media');
    }
}

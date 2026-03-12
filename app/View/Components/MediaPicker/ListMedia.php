<?php

namespace App\View\Components\MediaPicker;

use App\Models\CustomMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Unified Media Library Component
 *
 * Provides a consolidated interface for all media operations including:
 * - Media browsing and grid display
 * - File uploads with drag-and-drop
 * - Inline editing via modal
 * - Media selection for pickers
 * - Filtering and search
 *
 * @requirements 1.1, 1.3, 6.1, 6.3
 */
class ListMedia extends Component
{
    public $upload_settings = [];

    public $storage_data = [];

    public $mime_types;

    public $total_files;

    public $trashed_files;

    public $current_total;

    /**
     * @var string
     */
    public $status_slug = 'all';

    public $variation_config = [];

    /**
     * @var string
     */
    public $mode = 'management';

    /**
     * @var string
     */
    public $selection_mode = 'single';

    /**
     * @var int
     */
    public $max_selections = 1;

    /**
     * @var array<mixed>
     */
    public $allowed_types = [];

    public function __construct(
        private readonly CustomMedia $media,
        string $status = 'all',
        public $is_modal = false,
        string $mode = 'management',
        string $selectionMode = 'single',
        int $maxSelections = 1,
        array $allowedTypes = []
    ) {
        $this->status_slug = $status;
        $this->mode = $mode;
        $this->selection_mode = $selectionMode;
        $this->max_selections = $maxSelections;
        $this->allowed_types = $allowedTypes;
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        // Get upload settings from config
        $this->upload_settings = $this->getUploadSettings();

        // Get storage usage data
        $this->storage_data = $this->media->getUsedStorageSize();

        // Get variation configuration for responsive images
        $this->variation_config = config('media.variations', []);

        // Get available mime types for filtering
        $this->mime_types = DB::table('media')
            ->select('mime_type as label', 'mime_type as value')
            ->groupBy('mime_type')
            ->get()
            ->map(fn ($item): array => [
                'label' => $item->label,
                'value' => $item->value,
            ]);

        // Get file counts
        $this->total_files = $this->media->withoutTrashed()->count();
        $this->trashed_files = $this->media->onlyTrashed()->count();
        $this->current_total = $this->status_slug === 'trash' ? $this->trashed_files : $this->total_files;

        return view('components.media-picker.list-media');
    }

    /**
     * Get upload settings from configuration
     */
    protected function getUploadSettings(): array
    {
        $maxSizeMb = config('media-library.max_file_size', 100 * 1024 * 1024) / (1024 * 1024);
        $maxSizeBytes = config('media-library.max_file_size', 100 * 1024 * 1024);
        $acceptedMimeTypes = config('media.media_allowed_file_types', 'image/*,video/*,audio/*,application/pdf');

        return [
            'max_size_mb' => $maxSizeMb,
            'max_size_bytes' => $maxSizeBytes,
            'accepted_mime_types' => $acceptedMimeTypes,
            'friendly_file_types' => $this->getFriendlyFileTypes(explode(',', $acceptedMimeTypes)),
            'max_filename_length' => (int) config('media.max_file_name_length', 100),
            'upload_route' => route('app.media.upload-media'),
            'settings_route' => route('app.media.upload-settings'),
        ];
    }

    /**
     * Convert mime types to friendly display names
     */
    protected function getFriendlyFileTypes(array $mimeTypes): string
    {
        $types = [];

        foreach ($mimeTypes as $mime) {
            $mime = trim((string) $mime);
            if (str_starts_with($mime, 'image/') || $mime === 'image/*') {
                $types['Images'] = true;
            } elseif (str_starts_with($mime, 'video/') || $mime === 'video/*') {
                $types['Videos'] = true;
            } elseif (str_starts_with($mime, 'audio/') || $mime === 'audio/*') {
                $types['Audio'] = true;
            } elseif (str_contains($mime, 'pdf')) {
                $types['PDFs'] = true;
            } elseif (str_contains($mime, 'word') || str_contains($mime, 'document')) {
                $types['Documents'] = true;
            } elseif (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) {
                $types['Spreadsheets'] = true;
            } elseif (str_contains($mime, 'zip') || str_contains($mime, 'rar')) {
                $types['Archives'] = true;
            }
        }

        return implode(', ', array_keys($types));
    }
}

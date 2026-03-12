<?php

namespace App\View\Components\MediaPicker;

use App\Models\CustomMedia;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Illuminate\View\View;

class MediaModal extends Component
{
    public $title;

    /**
     * @var int
     */
    public $upload_media_flag;

    public function __construct($title = null, $upload_media_flag = 0, public $mediaconversion = 'original')
    {
        $this->title = $title ?? 'Media Picker';
        $this->upload_media_flag = (int) $upload_media_flag;
        try {
            $storage_data = CustomMedia::getUsedStorageSize();
            $this->upload_media_flag = (isset($storage_data['upload_enabled']) && $storage_data['upload_enabled'] === false ? 0 : 1);
        } catch (Exception $exception) {
            // If there's an error getting storage data, default to allowing uploads
            $this->upload_media_flag = 1;
            Log::warning('Failed to get storage size data for MediaModal', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('components.media-picker.media-modal');
    }
}

<?php

namespace App\View\Components\MediaPicker;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Gallery Media Picker Component
 *
 * A media picker component for selecting multiple images with preview gallery.
 * Integrates with the unified media library API.
 *
 * @requirements 5.4, 5.5 - Integration helpers for forms and components
 */
class GalleryPicker extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public string $name, public ?string $id = null, public array $value = [], public string $label = '', public string $type = 'image', public string $returnType = 'id', public int $maxSelections = 0, public int $minSelections = 0, public bool $sortable = false, public bool $required = false, public bool $disabled = false, public ?string $pickerTitle = null, public string $itemWidth = '120px', public string $itemHeight = '120px', public string $infoText = '') {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.media-picker.gallery-picker');
    }
}

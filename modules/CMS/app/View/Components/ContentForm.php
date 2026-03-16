<?php

namespace Modules\CMS\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ContentForm extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public $model = null, public $modelName = 'content', public $titlePlaceholder = 'Enter title', public $contentPlaceholder = 'Enter detailed content', public $preSlug = '/', public $metaRobotsOptions = [], public $showContentLabel = true) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        /** @var view-string $view */
        $view = 'cms::components.content-form';

        return view($view);
    }
}

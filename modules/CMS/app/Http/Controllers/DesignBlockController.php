<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use App\Traits\HasMediaPicker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\DesignBlockDefinition;
use Modules\CMS\Models\DesignBlock;
use Modules\CMS\Services\DesignBlockService;

class DesignBlockController extends ScaffoldController implements HasMiddleware
{
    use HasMediaPicker;

    public function __construct(private readonly DesignBlockService $designBlockService) {}

    public static function middleware(): array
    {
        return (new DesignBlockDefinition)->getMiddleware();
    }

    protected function service(): DesignBlockService
    {
        return $this->designBlockService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/design-blocks';
    }

    protected function getIndexViewData(Request $request): array
    {
        return [
            'designTypeOptions' => $this->designBlockService->getDesignTypeOptions(),
            'categoryOptions' => $this->designBlockService->getCategoryOptions(),
            'designSystemOptions' => $this->designBlockService->getDesignSystemOptions(),
        ];
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'initialValues' => $this->buildInitialValues($model),
            'statusOptions' => $this->designBlockService->getStatusOptions(),
            'designTypeOptions' => $this->designBlockService->getDesignTypeOptions(),
            'blockTypeOptions' => $this->designBlockService->getBlockTypeOptions(),
            'categoryOptions' => $this->designBlockService->getCategoryOptions(),
            'designSystemOptions' => $this->designBlockService->getDesignSystemOptions(),
            'defaults' => [
                'status' => 'draft',
                'design_type' => 'section',
                'block_type' => 'static',
                'design_system' => 'bootstrap',
                'category_id' => 'hero',
            ],
            ...$this->getMediaPickerProps(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var DesignBlock $model */
        return [
            'id' => $model->getKey(),
            'title' => (string) $model->getAttribute('title'),
            'updated_at_formatted' => app_date_time_format($model->updated_at, 'datetime'),
            'updated_at_human' => $model->updated_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialValues(Model $model): array
    {
        /** @var DesignBlock $model */
        return [
            'title' => (string) ($model->getAttribute('title') ?? ''),
            'slug' => (string) ($model->getAttribute('slug') ?? ''),
            'description' => (string) ($model->getAttribute('excerpt') ?? ''),
            'html' => (string) ($model->getAttribute('content') ?? ''),
            'css' => (string) ($model->getAttribute('css') ?? ''),
            'scripts' => (string) ($model->getAttribute('js') ?? ''),
            'preview_image_url' => (string) ($model->preview_image_url ?? ''),
            'design_type' => (string) ($model->design_type ?? 'section'),
            'block_type' => (string) ($model->block_type ?? 'static'),
            'design_system' => (string) ($model->design_system ?? 'bootstrap'),
            'category_id' => (string) ($model->category_id ?? 'hero'),
            'status' => (string) ($model->getAttribute('status') ?? 'draft'),
        ];
    }
}

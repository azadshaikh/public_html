<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\DesignBlockDefinition;
use Modules\CMS\Services\DesignBlockService;

class DesignBlockController extends ScaffoldController implements HasMiddleware
{
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

    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->designBlockService->getStatusOptions(),
            'defaults' => [
                'status' => 'draft',
                'design_type' => 'section',
                'block_type' => 'static',
                'design_system' => 'bootstrap',
                'category_id' => 'hero',
                'version' => 1,
            ],
        ];
    }
}

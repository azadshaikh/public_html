<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\FormDefinition;
use Modules\CMS\Services\FormService;

class FormController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly FormService $formService
    ) {}

    public static function middleware(): array
    {
        return (new FormDefinition)->getMiddleware();
    }

    protected function service(): FormService
    {
        return $this->formService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/forms';
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->formService->getStatusOptions(),
        ];
    }
}

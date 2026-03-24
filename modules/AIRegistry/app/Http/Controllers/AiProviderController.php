<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\AIRegistry\Definitions\AiProviderDefinition;
use Modules\AIRegistry\Models\AiProvider;
use Modules\AIRegistry\Services\AiProviderService;

class AiProviderController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly AiProviderService $aiProviderService
    ) {}

    public static function middleware(): array
    {
        return (new AiProviderDefinition)->getMiddleware();
    }

    protected function service(): AiProviderService
    {
        return $this->aiProviderService;
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var AiProvider $model */
        return [
            'initialValues' => [
                'slug' => (string) ($model->getAttribute('slug') ?? ''),
                'name' => (string) ($model->getAttribute('name') ?? ''),
                'docs_url' => (string) ($model->getAttribute('docs_url') ?? ''),
                'api_key_url' => (string) ($model->getAttribute('api_key_url') ?? ''),
                'capabilities' => $this->normalizeStringArray($model->getAttribute('capabilities')),
                'is_active' => $model->exists ? (bool) $model->getAttribute('is_active') : true,
            ],
            'capabilityOptions' => $this->aiProviderService->getCapabilityOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var AiProvider $model */
        return [
            'id' => (int) $model->getKey(),
            'name' => (string) $model->getAttribute('name'),
            'slug' => (string) $model->getAttribute('slug'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && $item !== '')
            ->values()
            ->all();
    }
}

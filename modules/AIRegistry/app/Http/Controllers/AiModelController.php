<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\AIRegistry\Definitions\AiModelDefinition;
use Modules\AIRegistry\Models\AiModel;
use Modules\AIRegistry\Services\AiModelService;
use Modules\AIRegistry\Services\AiProviderService;

class AiModelController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly AiModelService $aiModelService,
        private readonly AiProviderService $aiProviderService,
    ) {}

    public static function middleware(): array
    {
        return (new AiModelDefinition)->getMiddleware();
    }

    protected function service(): AiModelService
    {
        return $this->aiModelService;
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var AiModel $model */
        $selectedProviderId = $model->exists ? (int) $model->getAttribute('provider_id') : null;
        $supportedParameters = $model->getAttribute('supported_parameters');

        return [
            'initialValues' => [
                'provider_id' => $selectedProviderId !== null ? (string) $selectedProviderId : '',
                'slug' => (string) ($model->getAttribute('slug') ?? ''),
                'name' => (string) ($model->getAttribute('name') ?? ''),
                'description' => (string) ($model->getAttribute('description') ?? ''),
                'context_window' => $model->getAttribute('context_window') !== null
                    ? (string) $model->getAttribute('context_window')
                    : '',
                'max_output_tokens' => $model->getAttribute('max_output_tokens') !== null
                    ? (string) $model->getAttribute('max_output_tokens')
                    : '',
                'input_cost_per_1m' => $model->getAttribute('input_cost_per_1m') !== null
                    ? (string) $model->getAttribute('input_cost_per_1m')
                    : '',
                'output_cost_per_1m' => $model->getAttribute('output_cost_per_1m') !== null
                    ? (string) $model->getAttribute('output_cost_per_1m')
                    : '',
                'input_modalities' => $this->normalizeStringArray($model->getAttribute('input_modalities')),
                'output_modalities' => $this->normalizeStringArray($model->getAttribute('output_modalities')),
                'tokenizer' => (string) ($model->getAttribute('tokenizer') ?? ''),
                'is_moderated' => (bool) ($model->getAttribute('is_moderated') ?? false),
                'supported_parameters' => is_array($supportedParameters)
                    ? implode(', ', array_filter($supportedParameters, 'is_string'))
                    : (string) ($supportedParameters ?? ''),
                'capabilities' => $this->normalizeStringArray($model->getAttribute('capabilities')),
                'categories' => $this->normalizeStringArray($model->getAttribute('categories')),
                'is_active' => $model->exists ? (bool) $model->getAttribute('is_active') : true,
            ],
            'providerOptions' => $this->aiProviderService->getFormProviderOptions($selectedProviderId),
            'capabilityOptions' => $this->aiModelService->getCapabilityOptions(),
            'categoryOptions' => $this->aiModelService->getCategoryOptions(),
            'inputModalityOptions' => $this->aiModelService->getInputModalityOptions(),
            'outputModalityOptions' => $this->aiModelService->getOutputModalityOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var AiModel $model */
        $model->loadMissing('provider:id,name');

        return [
            'id' => (int) $model->getKey(),
            'name' => (string) $model->getAttribute('name'),
            'slug' => (string) $model->getAttribute('slug'),
            'provider_name' => (string) ($model->provider?->name ?? ''),
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

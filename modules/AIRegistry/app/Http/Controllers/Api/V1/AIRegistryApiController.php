<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\AIRegistry\Models\AiModel;
use Modules\AIRegistry\Models\AiProvider;

/**
 * Public read-only API for AI registry models.
 *
 * Public API is authenticated via X-Website-Key header.
 * Admin API is authenticated via normal web auth + module access middleware.
 */
class AIRegistryApiController extends Controller
{
    public function providers(): JsonResponse
    {
        $providers = AiProvider::query()
            ->where('is_active', true)
            ->select(['id', 'slug', 'name', 'capabilities', 'docs_url'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $providers,
        ]);
    }

    public function providerModels(string $providerSlug): JsonResponse
    {
        $provider = AiProvider::query()
            ->where('slug', $providerSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $models = AiModel::query()
            ->where('provider_id', $provider->id)
            ->where('is_active', true)
            ->select([
                'id', 'slug', 'name', 'description', 'context_window', 'max_output_tokens',
                'input_cost_per_1m', 'output_cost_per_1m', 'input_modalities', 'output_modalities',
                'tokenizer', 'is_moderated', 'supported_parameters', 'capabilities', 'categories',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $models,
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->buildResponse(),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->buildResponse(),
        ]);
    }

    private function buildResponse(): array
    {
        $models = AiModel::query()
            ->where('is_active', true)
            ->whereHas('provider', fn ($q) => $q->where('is_active', true))
            ->with('provider:id,slug,name,capabilities')
            ->orderBy('name')
            ->get();

        $grouped = [];

        foreach ($models as $model) {
            $providerSlug = $model->provider?->slug ?? 'unknown';

            if (! isset($grouped[$providerSlug])) {
                $grouped[$providerSlug] = [
                    'provider' => [
                        'slug' => $model->provider?->slug,
                        'name' => $model->provider?->name,
                        'capabilities' => $model->provider?->capabilities ?? [],
                    ],
                    'models' => [],
                ];
            }

            $grouped[$providerSlug]['models'][] = [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
                'description' => $model->description,
                'context_window' => $model->context_window,
                'max_output_tokens' => $model->max_output_tokens,
                'input_cost_per_1m' => $model->input_cost_per_1m,
                'output_cost_per_1m' => $model->output_cost_per_1m,
                'input_modalities' => $model->input_modalities ?? [],
                'output_modalities' => $model->output_modalities ?? [],
                'tokenizer' => $model->tokenizer,
                'is_moderated' => $model->is_moderated,
                'supported_parameters' => $model->supported_parameters ?? [],
                'capabilities' => $model->capabilities ?? [],
                'categories' => $model->categories ?? [],
            ];
        }

        return array_values($grouped);
    }
}

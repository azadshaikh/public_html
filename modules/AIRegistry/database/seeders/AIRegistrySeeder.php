<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AIRegistry\Models\AiProvider;
use Modules\AIRegistry\Services\OpenRouterModelSyncService;

class AIRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProviders();
        $this->syncOpenRouterModels();
        $this->resetSequences();
    }

    /**
     * Pull models from OpenRouter API for configured provider families.
     * Silently skips if no API key is available (e.g. CI without secrets).
     */
    private function syncOpenRouterModels(): void
    {
        $service = app(OpenRouterModelSyncService::class);

        if (! $service->hasApiKey()) {
            $this->command?->warn('OpenRouter API key not set — skipping model sync.');

            return;
        }

        try {
            $count = $service->sync();
            $this->command?->info("Synced {$count} OpenRouter model(s).");
        } catch (\Throwable $e) {
            Log::warning('OpenRouter model sync failed during seeding: '.$e->getMessage());
            $this->command?->warn('OpenRouter model sync failed: '.$e->getMessage());
        }
    }

    private function seedProviders(): void
    {
        $providers = [
            [
                'slug' => 'openai',
                'name' => 'OpenAI',
                'docs_url' => 'https://platform.openai.com/docs',
                'api_key_url' => 'https://platform.openai.com/api-keys',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming', 'embeddings', 'audio_input', 'audio_output', 'image_generation', 'reasoning'],
                'is_active' => true,
            ],
            [
                'slug' => 'anthropic',
                'name' => 'Anthropic',
                'docs_url' => 'https://docs.anthropic.com',
                'api_key_url' => 'https://console.anthropic.com/settings/keys',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming'],
                'is_active' => true,
            ],
            [
                'slug' => 'gemini',
                'name' => 'Google Gemini',
                'docs_url' => 'https://ai.google.dev/gemini-api/docs',
                'api_key_url' => 'https://aistudio.google.com/app/apikey',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming', 'embeddings', 'audio_input'],
                'is_active' => true,
            ],
            [
                'slug' => 'azure',
                'name' => 'Azure OpenAI',
                'docs_url' => 'https://learn.microsoft.com/azure/ai-services/openai/',
                'api_key_url' => 'https://portal.azure.com',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming', 'embeddings', 'image_generation'],
                'is_active' => true,
            ],
            [
                'slug' => 'xai',
                'name' => 'xAI (Grok)',
                'docs_url' => 'https://docs.x.ai',
                'api_key_url' => 'https://console.x.ai',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming'],
                'is_active' => true,
            ],
            [
                'slug' => 'openrouter',
                'name' => 'OpenRouter',
                'docs_url' => 'https://openrouter.ai/docs',
                'api_key_url' => 'https://openrouter.ai/keys',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming'],
                'is_active' => true,
            ],
            [
                'slug' => 'groq',
                'name' => 'Groq',
                'docs_url' => 'https://console.groq.com/docs',
                'api_key_url' => 'https://console.groq.com/keys',
                'capabilities' => ['text', 'function_calling', 'streaming'],
                'is_active' => true,
            ],
            [
                'slug' => 'deepseek',
                'name' => 'DeepSeek',
                'docs_url' => 'https://api-docs.deepseek.com',
                'api_key_url' => 'https://platform.deepseek.com/api_keys',
                'capabilities' => ['text', 'function_calling', 'streaming', 'reasoning'],
                'is_active' => true,
            ],
            [
                'slug' => 'mistral',
                'name' => 'Mistral AI',
                'docs_url' => 'https://docs.mistral.ai',
                'api_key_url' => 'https://console.mistral.ai/api-keys/',
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming', 'embeddings'],
                'is_active' => true,
            ],
            [
                'slug' => 'cohere',
                'name' => 'Cohere',
                'docs_url' => 'https://docs.cohere.com',
                'api_key_url' => 'https://dashboard.cohere.com/api-keys',
                'capabilities' => ['text', 'function_calling', 'streaming', 'embeddings', 'reranking'],
                'is_active' => true,
            ],
            [
                'slug' => 'ollama',
                'name' => 'Ollama',
                'docs_url' => 'https://github.com/ollama/ollama/blob/main/docs/api.md',
                'api_key_url' => null,
                'capabilities' => ['text', 'vision', 'function_calling', 'streaming', 'embeddings'],
                'is_active' => true,
            ],
        ];

        foreach ($providers as $provider) {
            AiProvider::query()->updateOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }

    private function resetSequences(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('airegistry_providers', 'id'), COALESCE((SELECT MAX(id) FROM airegistry_providers), 1))");
            DB::statement("SELECT setval(pg_get_serial_sequence('airegistry_models', 'id'), COALESCE((SELECT MAX(id) FROM airegistry_models), 1))");
        }
    }
}

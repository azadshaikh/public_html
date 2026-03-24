<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Services;

use App\Models\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AIRegistry\Models\AiModel;
use Modules\AIRegistry\Models\AiProvider;
use Throwable;

class OpenRouterModelSyncService
{
    /**
     * @var array<int, string>
     */
    private const IMPORTED_PROVIDER_PREFIXES = [
        'openai',
        'google',
        'anthropic',
        'minimax',
        'moonshotai',
        'x-ai',
        'z-ai',
    ];

    /**
     * @return array<int, string>
     */
    public function importedProviderPrefixes(): array
    {
        return self::IMPORTED_PROVIDER_PREFIXES;
    }

    public function hasApiKey(): bool
    {
        return $this->resolveApiKey() !== '';
    }

    public function sync(): int
    {
        $provider = AiProvider::query()->where('slug', 'openrouter')->first();

        if (! $provider) {
            return 0;
        }

        $apiKey = $this->resolveApiKey();

        if ($apiKey === '') {
            return 0;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(30)
                ->get('https://openrouter.ai/api/v1/models');

            if ($response->failed()) {
                Log::warning('OpenRouter model sync failed.', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return 0;
            }

            $payloads = $response->json('data', []);

            if (! is_array($payloads)) {
                return 0;
            }

            $syncedSlugs = [];

            foreach ($payloads as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                if (! $this->shouldImportPayload($payload)) {
                    continue;
                }

                $attributes = $this->mapPayload($provider->id, $payload);

                if ($attributes === null) {
                    continue;
                }

                $syncedSlugs[] = $attributes['slug'];

                AiModel::query()->updateOrCreate(
                    ['provider_id' => $provider->id, 'slug' => $attributes['slug']],
                    $attributes
                );
            }

            if ($syncedSlugs !== []) {
                AiModel::query()
                    ->where('provider_id', $provider->id)
                    ->whereNotIn('slug', $syncedSlugs)
                    ->forceDelete();
            }

            return count($syncedSlugs);
        } catch (Throwable $exception) {
            Log::warning('OpenRouter model sync threw an exception.', [
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldImportPayload(array $payload): bool
    {
        $slug = (string) ($payload['canonical_slug'] ?? $payload['id'] ?? '');

        if ($slug === '' || ! str_contains($slug, '/')) {
            return false;
        }

        return in_array(Str::before($slug, '/'), self::IMPORTED_PROVIDER_PREFIXES, true);
    }

    private function resolveApiKey(): string
    {
        $configuredApiKey = trim((string) config('ai.providers.openrouter.key', ''));

        if ($configuredApiKey !== '') {
            return $configuredApiKey;
        }

        $storedApiKey = trim((string) Settings::query()
            ->where('key', 'chatbot_api_key')
            ->value('value'));

        if ($storedApiKey === '') {
            return '';
        }

        try {
            return Crypt::decryptString($storedApiKey);
        } catch (DecryptException) {
            return $storedApiKey;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function mapPayload(int $providerId, array $payload): ?array
    {
        $slug = (string) ($payload['canonical_slug'] ?? $payload['id'] ?? '');

        if ($slug === '') {
            return null;
        }

        $inputModalities = $this->normalizeStringList(data_get($payload, 'architecture.input_modalities', []));
        $outputModalities = $this->normalizeStringList(data_get($payload, 'architecture.output_modalities', []));
        $supportedParameters = $this->normalizeStringList($payload['supported_parameters'] ?? []);
        $capabilities = $this->detectCapabilities($slug, $payload, $inputModalities, $outputModalities, $supportedParameters);

        return [
            'provider_id' => $providerId,
            'slug' => $slug,
            'name' => (string) ($payload['name'] ?? $slug),
            'description' => $this->nullableString($payload['description'] ?? null),
            'context_window' => $this->nullableInteger(data_get($payload, 'top_provider.context_length') ?? ($payload['context_length'] ?? null)),
            'max_output_tokens' => $this->nullableInteger(data_get($payload, 'top_provider.max_completion_tokens')),
            'input_cost_per_1m' => $this->costPerMillion(data_get($payload, 'pricing.prompt')),
            'output_cost_per_1m' => $this->costPerMillion(data_get($payload, 'pricing.completion')),
            'input_modalities' => $inputModalities !== [] ? $inputModalities : null,
            'output_modalities' => $outputModalities !== [] ? $outputModalities : null,
            'tokenizer' => $this->nullableString(data_get($payload, 'architecture.tokenizer')),
            'is_moderated' => $this->nullableBoolean(data_get($payload, 'top_provider.is_moderated')),
            'supported_parameters' => $supportedParameters !== [] ? $supportedParameters : null,
            'capabilities' => $capabilities !== [] ? $capabilities : null,
            'categories' => ['openrouter'],
            'is_active' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $trimmed = trim($item);

            if ($trimmed === '') {
                continue;
            }

            $normalized[] = Str::lower($trimmed);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $inputModalities
     * @param  array<int, string>  $outputModalities
     * @param  array<int, string>  $supportedParameters
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function detectCapabilities(
        string $slug,
        array $payload,
        array $inputModalities,
        array $outputModalities,
        array $supportedParameters,
    ): array {
        $capabilities = [];
        $allModalities = array_values(array_unique(array_merge($inputModalities, $outputModalities)));
        $supportedParameterSet = array_flip($supportedParameters);

        if (in_array('text', $allModalities, true)) {
            $capabilities[] = 'text';
            $capabilities[] = 'streaming';
        }

        if (in_array('image', $inputModalities, true)) {
            $capabilities[] = 'vision';
        }

        if (in_array('audio', $inputModalities, true)) {
            $capabilities[] = 'audio_input';
        }

        if (in_array('audio', $outputModalities, true)) {
            $capabilities[] = 'audio_output';
        }

        if (in_array('image', $outputModalities, true)) {
            $capabilities[] = 'image_generation';
        }

        if (in_array('embeddings', $outputModalities, true) || in_array('embedding', $outputModalities, true)) {
            $capabilities[] = 'embeddings';
        }

        if (isset($supportedParameterSet['tools']) || isset($supportedParameterSet['tool_choice']) || isset($supportedParameterSet['functions'])) {
            $capabilities[] = 'function_calling';
        }

        $haystack = Str::lower($slug.' '.(string) ($payload['name'] ?? '').' '.(string) ($payload['description'] ?? ''));

        if (Str::contains($haystack, ['reason', 'o1', 'o3'])) {
            $capabilities[] = 'reasoning';
        }

        return array_values(array_unique($capabilities));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function costPerMillion(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $perMillionCost = round((float) $value * 1000000, 4);

        if ($perMillionCost < 0 || $perMillionCost >= 1000000) {
            return null;
        }

        return $perMillionCost;
    }
}

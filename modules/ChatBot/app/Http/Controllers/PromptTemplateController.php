<?php

namespace Modules\ChatBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleManifest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Http\Requests\PromptTemplateRequest;
use Modules\ChatBot\Models\PromptTemplate;

class PromptTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $prompts = PromptTemplate::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('name', 'ilike', "%{$filters['search']}%")
                        ->orWhere('slug', 'ilike', "%{$filters['search']}%")
                        ->orWhere('purpose', 'ilike', "%{$filters['search']}%");
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString()
            ->through(fn (PromptTemplate $prompt): array => [
                'id' => $prompt->id,
                'name' => $prompt->name,
                'slug' => $prompt->slug,
                'purpose' => $prompt->purpose,
                'model' => $prompt->model,
                'tone' => $prompt->tone,
                'status' => $prompt->status,
                'is_default' => $prompt->is_default,
            ]);

        return Inertia::render('chatbot/index', [
            'module' => $this->module()->toSharedArray(),
            'filters' => $filters,
            'prompts' => $prompts,
            'stats' => [
                'total' => PromptTemplate::query()->count(),
                'active' => PromptTemplate::query()->where('status', 'active')->count(),
                'draft' => PromptTemplate::query()->where('status', 'draft')->count(),
                'defaults' => PromptTemplate::query()->where('is_default', true)->count(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('chatbot/create', [
            'module' => $this->module()->toSharedArray(),
            'prompt' => null,
            'initialValues' => PromptTemplate::defaultFormData(),
            'options' => $this->options(),
        ]);
    }

    public function store(PromptTemplateRequest $request): RedirectResponse
    {
        PromptTemplate::query()->create($request->promptAttributes());

        return to_route('chatbot.index')->with('status', 'Prompt template created.');
    }

    public function edit(PromptTemplate $promptTemplate): Response
    {
        return Inertia::render('chatbot/edit', [
            'module' => $this->module()->toSharedArray(),
            'prompt' => [
                'id' => $promptTemplate->id,
                'name' => $promptTemplate->name,
            ],
            'initialValues' => [
                'name' => $promptTemplate->name,
                'slug' => $promptTemplate->slug,
                'purpose' => $promptTemplate->purpose,
                'model' => $promptTemplate->model,
                'tone' => $promptTemplate->tone,
                'system_prompt' => $promptTemplate->system_prompt,
                'notes' => $promptTemplate->notes ?? '',
                'status' => $promptTemplate->status,
                'is_default' => $promptTemplate->is_default,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(PromptTemplateRequest $request, PromptTemplate $promptTemplate): RedirectResponse
    {
        $promptTemplate->update($request->promptAttributes());

        return to_route('chatbot.index')->with('status', 'Prompt template updated.');
    }

    public function destroy(PromptTemplate $promptTemplate): RedirectResponse
    {
        $promptTemplate->delete();

        return to_route('chatbot.index')->with('status', 'Prompt template deleted.');
    }

    /**
     * @return array{search: string, status: string}
     */
    protected function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $this->sanitizeFilter((string) $request->query('status', ''), array_keys(PromptTemplate::STATUSES)),
        ];
    }

    /**
     * @return array{statusOptions: array<int, array{value: string, label: string}>, toneOptions: array<int, array{value: string, label: string}>}
     */
    protected function options(): array
    {
        return [
            'statusOptions' => collect(PromptTemplate::STATUSES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'toneOptions' => collect(PromptTemplate::TONES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    protected function sanitizeFilter(string $value, array $allowed): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    protected function module(): ModuleManifest
    {
        return app(ModuleManager::class)->findOrFail('chatbot');
    }
}

<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Modules\CMS\Definitions\FormDefinition;
use Modules\CMS\Http\Resources\FormResource;

class FormService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new FormDefinition;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'published', 'label' => 'Published'],
            ['value' => 'draft', 'label' => 'Draft'],
        ];
    }

    public function getTemplateOptions(): array
    {
        return collect(config('cms::forms.templates', []))
            ->map(fn (array $template): array => [
                'value' => $template['value'] ?? null,
                'label' => $template['label'] ?? ucfirst((string) ($template['value'] ?? '')),
            ])
            ->reject(fn (array $option): bool => empty($option['value']))
            ->values()
            ->all();
    }

    public function getFormTypeOptions(): array
    {
        return collect(config('cms::forms.form_types', []))
            ->map(fn (array $type): array => [
                'value' => $type['value'] ?? null,
                'label' => $type['label'] ?? ucfirst((string) ($type['value'] ?? '')),
            ])
            ->reject(fn (array $option): bool => empty($option['value']))
            ->values()
            ->all();
    }

    protected function getResourceClass(): ?string
    {
        return FormResource::class;
    }

    protected function prepareCreateData(array $data): array
    {
        $confirmations = null;
        if (! empty($data['confirmation_type'])) {
            $confirmations = [
                'type' => $data['confirmation_type'],
            ];

            if ($data['confirmation_type'] === 'message' && ! empty($data['confirmation_message'])) {
                $confirmations['message'] = $data['confirmation_message'];
            } elseif ($data['confirmation_type'] === 'redirect' && ! empty($data['redirect_url'])) {
                $confirmations['redirect'] = $data['redirect_url'];
            }
        }

        $storeInDatabase = $data['store_in_database'] ?? true;
        if (is_string($storeInDatabase)) {
            $storeInDatabase = $storeInDatabase === 'true' || $storeInDatabase === '1';
        }

        $status = $data['status'] ?? 'draft';
        $publishedAt = $data['published_at'] ?? null;
        if ($status === 'published' && empty($publishedAt)) {
            $publishedAt = now();
        }

        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'shortcode' => $data['shortcode'] ?? null,
            'template' => $data['template'] ?? 'default',
            'form_type' => $data['form_type'] ?? 'standard',
            'html' => $data['html'] ?? null,
            'css' => $data['css'] ?? null,
            'confirmations' => $confirmations,
            'store_in_database' => $storeInDatabase,
            'status' => $status,
            'is_active' => $data['is_active'] ?? true,
            'published_at' => $publishedAt,
        ];
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareCreateData($data);
    }
}

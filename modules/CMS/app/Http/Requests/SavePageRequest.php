<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for saving page content from the Astero Builder.
 *
 * Expected payload:
 * {
 *   "content": "<html>...",
 *   "css": "body { ... }",
 *   "js": "console.log(...)",
 *   "format": "pagebuilder"
 * }
 */
class SavePageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'content' => ['present', 'string', 'max:2000000'],
            'css' => ['nullable', 'string', 'max:500000'],
            'js' => ['nullable', 'string', 'max:500000'],
            'format' => ['nullable', 'string', 'in:pagebuilder,html'],
            'builder_state' => ['nullable', 'array'],
            'builder_state.css' => ['nullable', 'string', 'max:500000'],
            'builder_state.js' => ['nullable', 'string', 'max:500000'],
            'builder_state.items' => ['nullable', 'array'],
            'builder_state.items.*.uid' => ['required_with:builder_state.items', 'string', 'max:255'],
            'builder_state.items.*.catalog_id' => ['nullable', 'string', 'max:255'],
            'builder_state.items.*.type' => ['required_with:builder_state.items', 'string', 'max:255'],
            'builder_state.items.*.category' => ['required_with:builder_state.items', 'string', 'max:255'],
            'builder_state.items.*.label' => ['required_with:builder_state.items', 'string', 'max:255'],
            'builder_state.items.*.html' => ['required_with:builder_state.items', 'string', 'max:2000000'],
            'builder_state.items.*.css' => ['nullable', 'string', 'max:500000'],
            'builder_state.items.*.js' => ['nullable', 'string', 'max:500000'],
            'builder_state.items.*.preview_image_url' => ['nullable', 'string', 'max:2048'],
            'builder_state.items.*.source' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Page content is required.',
            'content.string' => 'Page content must be valid HTML.',
            'content.max' => 'Page content exceeds the maximum allowed size.',
            'css.max' => 'CSS content exceeds the maximum allowed size.',
            'js.max' => 'JavaScript content exceeds the maximum allowed size.',
        ];
    }

    /**
     * Check if this is a content update from the builder.
     */
    public function isContentUpdate(): bool
    {
        return $this->exists('content');
    }

    /**
     * Get sanitized content.
     * Note: Heavy sanitization is done in CMSService::updatePageContent()
     */
    public function getPageContent(): string
    {
        return $this->input('content', '');
    }

    /**
     * Get CSS content.
     */
    public function getCss(): ?string
    {
        return $this->input('css');
    }

    /**
     * Get JS content.
     */
    public function getJs(): ?string
    {
        return $this->input('js');
    }

    /**
     * Get structured builder state for React/Inertia builder round-tripping.
     *
     * @return array<string, mixed>|null
     */
    public function getBuilderState(): ?array
    {
        $builderState = $this->input('builder_state');

        return is_array($builderState) ? $builderState : null;
    }
}

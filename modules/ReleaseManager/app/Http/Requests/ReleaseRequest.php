<?php

declare(strict_types=1);

namespace Modules\ReleaseManager\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Modules\ReleaseManager\Definitions\ReleaseDefinition;

class ReleaseRequest extends ScaffoldRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $releaseTypes = collect(config('releasemanager.release_types', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $versionTypes = collect(config('releasemanager.version_types', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $statusOptions = collect(config('releasemanager.status_options', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $releaseTypes = $releaseTypes ?: ['application', 'module'];
        $versionTypes = $versionTypes ?: ['major', 'minor', 'patch'];
        $statusOptions = $statusOptions ?: ['draft', 'published', 'deprecate'];

        return [
            'package_identifier' => ['required', 'string', 'max:255'],
            'version' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('release_manager_releases', 'version')
                    ->where('release_type', $this->currentType())
                    ->where('package_identifier', $this->input('package_identifier'))
                    ->ignore($this->getRouteParameter()),
            ],
            'version_type' => ['required', Rule::in($versionTypes)],
            'status' => ['required', Rule::in($statusOptions)],
            'release_at' => ['nullable', 'date'],
            'change_log' => ['nullable', 'string'],
            'release_link' => ['required', 'string', 'max:500'],
            'release_type' => ['nullable', Rule::in($releaseTypes)],
            'file_name' => ['nullable', 'string', 'max:255'],
            'checksum' => ['nullable', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function fieldLabels(): array
    {
        return [
            'package_identifier' => 'Package Identifier',
            'version' => 'Version',
            'version_type' => 'Version Type',
            'status' => 'Status',
            'release_at' => 'Release Date',
            'change_log' => 'Change Log',
            'release_link' => 'Release Link',
            'release_type' => 'Release Type',
            'file_name' => 'File Name',
            'checksum' => 'Checksum',
            'file_size' => 'File Size',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new ReleaseDefinition;
    }

    /**
     * Override to use {id} route parameter instead of {release}
     */
    protected function getRouteParameter(): int|string|null
    {
        return $this->route('release');
    }

    protected function prepareForValidation(): void
    {
        $type = $this->currentType();

        // ReleaseManager uses a single CRUD for both application + module.
        // For application releases, package_identifier is always "main" and should not block validation.
        if ($type === 'application' && ! $this->filled('package_identifier')) {
            $this->merge(['package_identifier' => 'main']);
        }

        // Ensure persisted release_type always matches the current route context.
        if (! $this->filled('release_type')) {
            $this->merge(['release_type' => $type]);
        }
    }

    private function currentType(): string
    {
        return (string) ($this->route('type') ?? $this->input('type', 'application'));
    }
}

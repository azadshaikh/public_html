<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Modules\CMS\Definitions\RedirectionDefinition;
use Modules\CMS\Models\Redirection;

class RedirectionRequest extends ScaffoldRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->isMethod('POST')) {
            return $user->can('add_redirections');
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return $user->can('edit_redirections');
        }

        if ($user->can('add_redirections')) {
            return true;
        }

        return (bool) $user->can('edit_redirections');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $redirectTypes = array_map(
            static fn (int|string $value): string => (string) $value,
            array_keys(config('seo.redirect_types', []))
        );

        $urlTypes = array_keys(config('seo.url_types', []));
        $matchTypes = array_keys(config('seo.match_types', []));

        $rules = [
            'redirect_type' => ['required', Rule::in($redirectTypes)],
            'url_type' => ['required', Rule::in($urlTypes)],
            'match_type' => ['required', Rule::in($matchTypes)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];

        $redirectionId = $this->getRouteParameter();
        $matchType = $this->input('match_type', 'exact');

        // Source URL validation based on match_type
        $rules['source_url'] = $this->getSourceUrlRules($matchType, $redirectionId);

        // Target URL validation based on url_type
        $urlType = $this->input('url_type');
        $rules['target_url'] = $this->getTargetUrlRules($urlType, $matchType);

        // Add circular redirect validation
        $rules['target_url'][] = function (string $attribute, mixed $value, Closure $fail): void {
            $this->validateNoCircularRedirect($value, $fail);
        };

        $rules['metadata'] = [
            'nullable',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_null($value) || $value === '' || $value === []) {
                    return;
                }

                if (is_array($value)) {
                    return;
                }

                if (is_string($value)) {
                    json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return;
                    }

                    $fail('Metadata must be valid JSON.');

                    return;
                }

                $fail('Metadata format is invalid.');
            },
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'source_url.required' => 'Please enter the source URL (the "from" path).',
            'source_url.regex' => 'The source URL contains invalid characters. Use only letters, numbers, hyphens, underscores, and slashes.',
            'source_url.unique' => 'A redirect from this source URL already exists.',
            'source_url.max' => 'The source URL must not exceed 512 characters.',
            'source_url.starts_with' => 'The source URL must start with a forward slash (/).',
            'target_url.required' => 'Please enter the target URL (the "to" destination).',
            'target_url.regex' => 'The target URL contains invalid characters.',
            'target_url.url' => 'Please enter a valid URL (e.g., https://example.com/page).',
            'target_url.max' => 'The target URL must not exceed 1024 characters.',
            'redirect_type.required' => 'Please select a redirect type.',
            'redirect_type.in' => 'Please select a valid redirect type (301, 302, 307, or 308).',
            'url_type.required' => 'Please select whether the target is internal or external.',
            'url_type.in' => 'Please select a valid URL type.',
            'match_type.required' => 'Please select a matching type.',
            'match_type.in' => 'Please select a valid matching type (exact, wildcard, or regex).',
            'status.required' => 'Please select a status.',
            'status.in' => 'Please select a valid status (active or inactive).',
            'notes.max' => 'Notes cannot exceed 5000 characters.',
            'expires_at.date' => 'Please enter a valid expiration date.',
            'expires_at.after' => 'The expiration date must be in the future.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new RedirectionDefinition;
    }

    /**
     * Get source URL validation rules based on match type.
     */
    protected function getSourceUrlRules(string $matchType, mixed $redirectionId): array
    {
        $baseRules = [
            'required',
            'string',
            'max:512',
        ];

        if ($matchType === 'exact') {
            return array_merge($baseRules, [
                'starts_with:/',
                'regex:/^\/[a-zA-Z0-9\-\/_\.\?\&\=\%\#]*$/',
                Rule::unique('cms_redirections', 'source_url')
                    ->ignore($redirectionId)
                    ->whereNull('deleted_at'),
            ]);
        }

        if ($matchType === 'wildcard') {
            return array_merge($baseRules, [
                'starts_with:/',
                'regex:/^\/[a-zA-Z0-9\-\/_\.\*]*$/', // Allow * for wildcards
                Rule::unique('cms_redirections', 'source_url')
                    ->ignore($redirectionId)
                    ->whereNull('deleted_at'),
            ]);
        }

        if ($matchType === 'regex') {
            return array_merge($baseRules, [
                function (string $attribute, mixed $value, Closure $fail): void {
                    $this->validateRegexPattern($value, $fail);
                },
                Rule::unique('cms_redirections', 'source_url')
                    ->ignore($redirectionId)
                    ->whereNull('deleted_at'),
            ]);
        }

        return $baseRules;
    }

    /**
     * Get target URL validation rules based on URL type.
     */
    protected function getTargetUrlRules(string $urlType, string $matchType): array
    {
        $baseRules = [
            'required',
            'string',
            'max:1024',
        ];

        if ($urlType === 'internal') {
            // For regex/wildcard, allow $1, $2 placeholders in target
            if ($matchType === 'regex' || $matchType === 'wildcard') {
                return array_merge($baseRules, [
                    'regex:/^\/[a-zA-Z0-9\-\/_\.\$\?\&\=\%]*$/',
                ]);
            }

            return array_merge($baseRules, [
                'regex:/^\/[a-zA-Z0-9\-\/_\.\?\&\=\%]*$/',
            ]);
        }

        if ($urlType === 'external') {
            return array_merge($baseRules, [
                'url',
                'regex:/^https?:\/\/.+/',
            ]);
        }

        return $baseRules;
    }

    /**
     * Validate that regex pattern is valid.
     */
    protected function validateRegexPattern(mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The regex pattern must be a string.');

            return;
        }

        // Test if the regex is valid
        set_error_handler(static fn (int $errno, string $errstr, string $errfile, int $errline): bool => true);
        $result = @preg_match('#'.$value.'#', '');
        restore_error_handler();

        if ($result === false) {
            $fail('The regex pattern is invalid. Please check your syntax.');
        }
    }

    /**
     * Validate that this redirect won't create a circular loop.
     */
    protected function validateNoCircularRedirect(mixed $targetUrl, Closure $fail): void
    {
        $sourceUrl = $this->input('source_url');
        $urlType = $this->input('url_type');
        $matchType = $this->input('match_type', 'exact');

        // Skip circular check for external URLs
        if ($urlType === 'external') {
            return;
        }

        // Direct circular redirect (source = target)
        if ($matchType === 'exact' && $sourceUrl === $targetUrl) {
            $fail('The target URL cannot be the same as the source URL (circular redirect).');

            return;
        }

        // Check for redirect chains (A -> B -> A)
        if ($matchType === 'exact') {
            $existingRedirect = Redirection::query()
                ->where('source_url', $targetUrl)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first();

            if ($existingRedirect) {
                // Check if this creates a chain back to source
                $visited = [$sourceUrl, $targetUrl];
                $currentTarget = $existingRedirect->target_url;
                $maxHops = 10;
                $hops = 0;

                while ($hops < $maxHops) {
                    if (in_array($currentTarget, $visited)) {
                        $fail('This redirect would create a circular redirect chain. The target URL "'.$targetUrl.'" already redirects, which would loop back to the source.');

                        return;
                    }

                    $visited[] = $currentTarget;

                    $nextRedirect = Redirection::query()
                        ->where('source_url', $currentTarget)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $nextRedirect) {
                        break;
                    }

                    $currentTarget = $nextRedirect->target_url;
                    $hops++;
                }

                if ($hops >= $maxHops) {
                    $fail('This redirect chain is too long (more than '.$maxHops.' hops). Consider simplifying your redirect rules.');
                }
            }
        }
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $sourceUrl = $this->has('source_url') ? trim((string) $this->source_url) : null;
        $matchType = $this->input('match_type', 'exact');

        // Ensure source URL starts with / for exact and wildcard matches
        if ($sourceUrl && $matchType !== 'regex' && ! str_starts_with($sourceUrl, '/')) {
            $sourceUrl = '/'.$sourceUrl;
        }

        $mergeData = [
            'source_url' => $sourceUrl,
            'target_url' => trim((string) $this->target_url),
            'redirect_type' => $this->has('redirect_type') ? (int) $this->redirect_type : null,
        ];

        // Handle empty expires_at
        if ($this->has('expires_at') && empty($this->expires_at)) {
            $mergeData['expires_at'] = null;
        }

        $this->merge($mergeData);

        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => trim((string) $this->metadata),
            ]);
        }
    }
}

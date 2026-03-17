<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\SecretDefinition;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;

class SecretRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $allowedTypes = array_keys(config('platform.secret_types', []));
        $allowedSecretableTypes = array_keys($this->secretableTypeMap());

        return [
            'secretable_type' => ['required', 'string', Rule::in($allowedSecretableTypes)],
            'secretable_id' => ['required', 'integer'],
            'key' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($allowedTypes)],
            'value' => $this->isUpdate() ? ['nullable', 'string'] : ['required', 'string'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $secretableType = (string) $this->input('secretable_type');
            $secretableId = $this->input('secretable_id');

            if ($secretableType === '' || $secretableId === null || $secretableId === '') {
                return;
            }

            $modelClass = $this->secretableTypeMap()[$secretableType] ?? null;

            if (! $modelClass || ! class_exists($modelClass)) {
                $validator->errors()->add('secretable_type', 'The selected model type is invalid.');

                return;
            }

            /** @var Builder $query */
            $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
                ? $modelClass::withTrashed()
                : $modelClass::query();

            if (! $query->whereKey((int) $secretableId)->exists()) {
                $validator->errors()->add('secretable_id', 'The selected model ID does not exist for the chosen model type.');
            }
        });
    }

    protected function definition(): ScaffoldDefinition
    {
        return new SecretDefinition;
    }

    /**
     * @return array<string, class-string>
     */
    private function secretableTypeMap(): array
    {
        return [
            Domain::class => Domain::class,
            Website::class => Website::class,
            Agency::class => Agency::class,
            Server::class => Server::class,
            Provider::class => Provider::class,
        ];
    }
}

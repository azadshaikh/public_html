<?php

declare(strict_types=1);

namespace App\View\Components\FormElements;

use App\Models\Geo\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Illuminate\View\View;
use Throwable;

class CountrySelect extends Component
{
    /**
     * @var array<int, array{value:string, label:string}>
     */
    public array $options;

    /**
     * @param  array<int, array{value:string, label:string}>|null  $options
     */
    public function __construct(
        ?array $options = null,
        public mixed $cacheTtl = null,
        public bool $useChoices = true,
    ) {
        $this->options = $this->resolveOptions($options);
    }

    public function render(): View
    {
        return view('components.form-elements.country-select');
    }

    /**
     * @param  array<int, array{value:string, label:string}>|null  $options
     * @return array<int, array{value:string, label:string}>
     */
    protected function resolveOptions(?array $options): array
    {
        if (is_array($options) && $options !== []) {
            return $options;
        }

        $cacheTtl = $this->cacheTtl ?? now()->addDays(7);

        try {
            $cached = Cache::remember('form-elements.country-select.options', $cacheTtl, fn (): array => $this->loadCountriesFromDatabase());

            if (! empty($cached)) {
                return $cached;
            }
        } catch (Throwable $throwable) {
            Log::error('Country select component - Cache error: '.$throwable->getMessage());
        }

        try {
            $direct = $this->loadCountriesFromDatabase();
            if ($direct !== []) {
                return $direct;
            }
        } catch (Throwable $throwable) {
            Log::error('Country select component - Direct fetch error: '.$throwable->getMessage());
        }

        return [
            ['value' => 'US', 'label' => 'United States'],
            ['value' => 'CA', 'label' => 'Canada'],
            ['value' => 'GB', 'label' => 'United Kingdom'],
            ['value' => 'AU', 'label' => 'Australia'],
            ['value' => 'DE', 'label' => 'Germany'],
            ['value' => 'FR', 'label' => 'France'],
            ['value' => 'IN', 'label' => 'India'],
            ['value' => 'JP', 'label' => 'Japan'],
            ['value' => 'BR', 'label' => 'Brazil'],
            ['value' => 'ZA', 'label' => 'South Africa'],
        ];
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    protected function loadCountriesFromDatabase(): array
    {
        return Country::all()
            ->map(function ($country): ?array {
                $code = strtoupper($country->iso2 ?? '');
                $name = $country->name ?? '';

                if ($code === '' || $name === '') {
                    return null;
                }

                return [
                    'value' => $code,
                    'label' => $name,
                ];
            })
            ->filter()
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}

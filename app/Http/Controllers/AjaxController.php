<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GeoDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AjaxController extends Controller
{
    public function fieldAttribute(Request $request)
    {
        $html_text = '';
        $field_type = $request->input('type');
        $field_types = Config('constants.form_field_types');

        if (isset($field_types[$field_type])) {
            $field_attributes = $field_types[$field_type]['attributes'];

            $data = ['field_attributes' => $field_attributes];
            $data['dynamicoptions'] = [
                'cms_categories' => 'CMS Categories',
                'cms_tags' => 'CMS Tags',
                'types' => 'Post Type',
                'menus' => 'Menus',
            ];
            $html_text .= view('components.field-attributes', $data)->render();
        }

        return response()->json(['status' => 'success', 'html_text' => $html_text]);
    }

    public function ajaxStatesByCountry(Request $request): JsonResponse
    {
        $country_code = $request->input('country_code') ?? $request->input('country_id');
        $selected_code = $request->input('selected_code') ?? $request->input('selected_id');

        $geoService = resolve(GeoDataService::class);
        $states = $geoService->getStatesByCountryCode($country_code);

        $option = '<option value="">Select State</option>';

        if (! empty($states)) {
            foreach ($states as $state) {
                $selected = $selected_code === $state['iso2'] ? 'selected' : '';
                $option .= '<option value="'.$state['iso2'].'" '.$selected.'>'.$state['name'].'</option>';
            }
        }

        return response()->json(['options' => $option, 'selected_code' => $selected_code]);
    }

    public function ajaxCitiesByState(Request $request): JsonResponse
    {
        $state_code = $request->input('state_code') ?? $request->input('state_id');
        $selected_id = $request->input('selected_id') ?? $request->input('selected_code');

        $geoService = resolve(GeoDataService::class);
        $cities = $geoService->getCitiesByStateCode($state_code);

        $option = '<option value="">Select City</option>';

        if (! empty($cities)) {
            foreach ($cities as $city) {
                $selected = $selected_id === $city['id'] ? 'selected' : '';
                $option .= '<option value="'.$city['id'].'" '.$selected.'>'.$city['name'].'</option>';
            }
        }

        return response()->json(['options' => $option, 'selected_id' => $selected_id]);
    }

    /**
     * Fetch users for searchable selects (limited to 50 results).
     */
    public function users(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min($limit, 50));

        $search = trim((string) $request->input('search', ''));
        $ids = $request->input('ids', []);

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $ids = array_values(array_filter(array_map(intval(...), (array) $ids)));

        $query = User::query()
            ->visibleToCurrentUser()
            ->select('id', 'name', 'email')
            ->orderBy('name');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            });
        }

        $users = $query->limit($limit)->get();

        $items = $users->map(fn (User $user): array => [
            'value' => (string) $user->id,
            'label' => sprintf('%s (%s)', $user->name, $user->email),
        ])->toArray();

        return response()->json(['items' => $items]);
    }

    /**
     * Return all countries as JSON options for geo select components.
     *
     * @return JsonResponse{items: array<int, array{value: string, label: string}>}
     */
    public function geoCountries(): JsonResponse
    {
        $countries = resolve(GeoDataService::class)->getAllCountries();

        $items = array_map(fn (array $c): array => [
            'value' => $c['iso2'],
            'label' => $c['name'],
        ], $countries);

        return response()->json(['items' => $items]);
    }

    /**
     * Return states for a given country as JSON options for geo select components.
     *
     * @return JsonResponse{items: array<int, array{value: string, label: string}>}
     */
    public function geoStates(Request $request): JsonResponse
    {
        $countryCode = (string) $request->input('country_code', '');

        if ($countryCode === '') {
            return response()->json(['items' => []]);
        }

        $states = resolve(GeoDataService::class)->getStatesByCountryCode($countryCode);

        $items = array_map(fn (array $s): array => [
            'value' => (string) ($s['iso3166_2'] ?? $s['iso2'] ?? ''),
            'label' => $s['name'],
        ], $states);

        return response()->json(['items' => $items]);
    }

    /**
     * Return cities for a given state as JSON options for geo select components.
     *
     * @return JsonResponse{items: array<int, array{value: string, label: string}>}
     */
    public function geoCities(Request $request): JsonResponse
    {
        $stateCode = (string) $request->input('state_code', '');
        $countryCode = (string) $request->input('country_code', '');

        if ($stateCode === '' && $countryCode === '') {
            return response()->json(['items' => []]);
        }

        $geoDataService = resolve(GeoDataService::class);

        $cities = $stateCode !== ''
            ? $geoDataService->getCitiesByStateCode($stateCode)
            : $geoDataService->getCitiesByCountryCode($countryCode);

        $items = array_map(fn (array $c): array => [
            'value' => (string) ($c['id'] ?? $c['name']),
            'label' => $c['name'],
        ], $cities);

        return response()->json(['items' => $items]);
    }
}

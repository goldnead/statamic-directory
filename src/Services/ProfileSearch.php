<?php

namespace Goldnead\Directory\Services;

use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Search\SearchEngine;
use Illuminate\Validation\Rule;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

class ProfileSearch
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public static function rules(): array
    {
        $rules = [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site' => ['sometimes', 'string', 'max:64'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(Collections::handles())],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:lng,radius_km'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:lat,radius_km'],
            'radius_km' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:'.config('directory.search.max_radius_km')],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.config('directory.search.max_per_page')],
        ];

        foreach (Collections::filterColumns() as $filter) {
            $rules[$filter] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $params  validated parameters, see rules()
     * @return array{data: list<array>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function search(array $params, string $site): array
    {
        $params = array_filter($params, fn ($value) => $value !== null && $value !== '');
        $perPage = min((int) ($params['per_page'] ?? config('directory.search.per_page')), (int) config('directory.search.max_per_page'));
        $page = max((int) ($params['page'] ?? 1), 1);

        $type = $params['type'] ?? Collections::default();
        abort_unless(Collections::has($type), 422);

        $query = DirectoryProfile::query()
            ->where('collection', $type)
            ->where('site', $site)
            ->where('published', true)
            ->where('hidden', false)
            ->where('visibility', 'public');

        if (isset($params['city'])) {
            $query->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($params['city']))]);
        }

        // Ranked entry ids from the search engine; null = no engine or engine failed, SQL decides.
        $ranked = isset($params['q']) ? $this->rank($type, $site, (string) $params['q']) : null;

        if ($ranked !== null) {
            $query->whereIn('entry_id', $ranked ?: ['']);
        } elseif (isset($params['q'])) {
            $term = '%'.mb_strtolower(trim($params['q'])).'%';
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(title) LIKE ?', [$term])->orWhereRaw('LOWER(city) LIKE ?', [$term]));
        }

        foreach (array_keys(Collections::filters($type)) as $filter) {
            if (isset($params[$filter])) {
                $query->where($filter, $params[$filter]);
            }
        }

        $origin = isset($params['lat'], $params['lng']) ? [(float) $params['lat'], (float) $params['lng']] : null;

        if ($origin) {
            $query->whereNotNull('lat')->whereNotNull('lng');

            if (isset($params['radius_km'])) {
                $this->boundingBox($query, $origin, (float) $params['radius_km']);
            }

            // ponytail: distance is computed in PHP over the bounding-box candidates; switch to a SQL
            // distance expression or PostGIS if a radius regularly matches many thousand rows.
            $rows = $query->get()
                ->each(fn (DirectoryProfile $p) => $p->setAttribute('distance_km', $this->haversine($origin, [$p->lat, $p->lng])));

            if (isset($params['radius_km'])) {
                $rows = $rows->where('distance_km', '<=', (float) $params['radius_km']);
            }

            $rows = $rows->sortBy([['distance_km', 'asc'], ['title', 'asc']])->values();
            $total = $rows->count();
            $rows = $rows->slice(($page - 1) * $perPage, $perPage);
        } elseif ($ranked !== null) {
            $position = array_flip($ranked);
            $rows = $query->get()->sortBy(fn (DirectoryProfile $p) => $position[$p->entry_id] ?? PHP_INT_MAX)->values();
            $total = $rows->count();
            $rows = $rows->slice(($page - 1) * $perPage, $perPage);
        } else {
            $total = (clone $query)->count();
            $rows = $query->orderBy('title')->orderBy('id')->offset(($page - 1) * $perPage)->limit($perPage)->get();
        }

        // Pages are cut from the index; a profile whose Entry turned non-public since the last
        // projection is dropped here, so a page can be shorter than per_page.
        $data = $rows->map(function (DirectoryProfile $profile) use ($params, $ranked): ?array {
            $entry = $this->publicEntry($profile);
            $data = $entry ? $this->publicData($profile, $entry) : null;

            if (! $data || ! $this->matchesCurrentData($data, $ranked !== null ? array_diff_key($params, ['q' => true]) : $params)) {
                return null;
            }

            return $profile->distance_km !== null ? $data + ['distance_km' => round($profile->distance_km, 1)] : $data;
        })->filter()->values()->all();

        return [
            'data' => $data,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max((int) ceil($total / $perPage), 1)],
        ];
    }

    public function publicEntry(DirectoryProfile $profile): ?EntryContract
    {
        if (! $profile->published || $profile->hidden || $profile->visibility !== 'public') {
            return null;
        }

        $entry = $this->canonicalEntry($profile);

        if (! $entry || ! $entry->published()) {
            return null;
        }

        $fields = Collections::fields($entry->collectionHandle());
        $data = $entry->data()->all();

        if (($data[$fields['visibility']] ?? null) !== 'public' || (bool) ($data[$fields['hidden']] ?? false)) {
            return null;
        }

        return $entry;
    }

    public function canonicalEntry(DirectoryProfile $profile): ?EntryContract
    {
        $entry = Entry::find($profile->entry_id);
        $entry = $entry?->in($profile->site);

        if (! $entry || (string) $entry->id() !== (string) $profile->entry_id
            || (string) $entry->locale() !== (string) $profile->site
            || $entry->collectionHandle() !== ($profile->collection ?? Collections::default())
            || ! Collections::has($entry->collectionHandle())) {
            return null;
        }

        return $entry;
    }

    public function publicData(DirectoryProfile $profile, EntryContract $entry): array
    {
        $fields = Collections::fields($entry->collectionHandle());
        $source = $entry->data()->all();

        return [
            'id' => $profile->entry_id,
            'type' => $entry->collectionHandle(),
            'site' => $profile->site,
            'title' => $source[$fields['title']] ?? $entry->get('title'),
            'city' => $source[$fields['city']] ?? null,
            'description' => $source[$fields['description']] ?? null,
        ];
    }

    /** @return list<string>|null */
    private function rank(string $type, string $site, string $q): ?array
    {
        $engine = app(SearchEngine::class);

        return $engine->enabled() ? $engine->search($type, $site, trim($q), (int) config('directory.search.max_hits')) : null;
    }

    private function matchesCurrentData(array $data, array $params): bool
    {
        if (isset($params['city']) && mb_strtolower(trim((string) $data['city'])) !== mb_strtolower(trim($params['city']))) {
            return false;
        }

        if (isset($params['q'])) {
            $haystack = mb_strtolower((string) $data['title'].' '.(string) $data['city']);

            return str_contains($haystack, mb_strtolower(trim($params['q'])));
        }

        return true;
    }

    // ponytail: ignores the antimeridian wrap; irrelevant for a DACH directory.
    private function boundingBox($query, array $origin, float $radiusKm): void
    {
        [$lat, $lng] = $origin;
        $dLat = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $query->whereBetween('lat', [$lat - $dLat, $lat + $dLat]);

        $cos = cos(deg2rad(min(abs($lat) + $dLat, 89.9)));
        $dLng = rad2deg($radiusKm / (self::EARTH_RADIUS_KM * $cos));

        if ($dLng < 180) {
            $query->whereBetween('lng', [$lng - $dLng, $lng + $dLng]);
        }
    }

    private function haversine(array $a, array $b): float
    {
        [$lat1, $lng1] = array_map('deg2rad', $a);
        [$lat2, $lng2] = array_map('deg2rad', $b);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1, sqrt($h)));
    }
}

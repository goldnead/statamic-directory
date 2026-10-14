<?php

namespace Goldnead\Directory\Services;

use Goldnead\Directory\Models\DirectoryProfile;
use Statamic\Facades\Collection;

class DirectoryStats
{
    /** @return array<int, array{handle: string, title: string, total: int, public: int, private: int, no_coordinates: int}> */
    public static function perType(): array
    {
        $rows = DirectoryProfile::query()
            ->selectRaw('collection, count(*) as total')
            ->selectRaw("sum(case when visibility = 'public' then 1 else 0 end) as public_count")
            ->selectRaw('sum(case when lat is null or lng is null then 1 else 0 end) as no_coordinates')
            ->groupBy('collection')
            ->get()
            ->keyBy('collection');

        return array_map(function (string $handle) use ($rows): array {
            $row = $rows->get($handle);
            $total = (int) ($row->total ?? 0);
            $public = (int) ($row->public_count ?? 0);

            return [
                'handle' => $handle,
                'title' => self::title($handle),
                'total' => $total,
                'public' => $public,
                'private' => $total - $public,
                'no_coordinates' => (int) ($row->no_coordinates ?? 0),
            ];
        }, Collections::handles());
    }

    public static function title(string $handle): string
    {
        $collection = Collection::findByHandle($handle);

        return $collection ? (string) $collection->title() : $handle;
    }
}

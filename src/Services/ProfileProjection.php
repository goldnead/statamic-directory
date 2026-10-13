<?php

namespace Goldnead\Directory\Services;

use Illuminate\Support\Facades\DB;
use Statamic\Contracts\Entries\Entry;

class ProfileProjection
{
    public function sync(Entry $entry): void
    {
        if ($entry->collectionHandle() !== config('directory.collection')) {
            $this->hide($entry);

            return;
        }

        $fields = config('directory.fields');
        $entryData = $entry->data()->all();
        $title = (string) ($entryData[$fields['title']] ?? $entry->get('title') ?? '');

        if ($title === '') {
            $this->hide($entry);

            return;
        }

        $filterValues = [];
        foreach (config('directory.filters', []) as $column => $field) {
            $filterValues[$column] = $this->nullableString($entryData[$field] ?? null);
        }

        $identity = ['entry_id' => (string) $entry->id(), 'site' => (string) $entry->locale()];
        $projectionValues = [
            'title' => $title,
            'city' => $this->nullableString($entryData[$fields['city']] ?? null),
            'description' => $this->nullableString($entryData[$fields['description']] ?? null),
            'lat' => $this->coordinate($entryData[$fields['lat']] ?? null, 90),
            'lng' => $this->coordinate($entryData[$fields['lng']] ?? null, 180),
            'visibility' => (string) ($entryData[$fields['visibility']] ?? 'private'),
            'hidden' => (bool) ($entryData[$fields['hidden']] ?? false),
            'published' => (bool) $entry->published(),
            'updated_at' => now(),
        ] + $filterValues;

        DB::table('directory_profiles')->upsert(
            [$identity + $projectionValues + ['created_at' => now()]],
            ['entry_id', 'site'],
            array_keys($projectionValues)
        );
    }

    public function forget(Entry $entry): void
    {
        DB::table('directory_profiles')
            ->where('entry_id', (string) $entry->id())
            ->where('site', (string) $entry->locale())
            ->delete();
    }

    private function hide(Entry $entry): void
    {
        DB::table('directory_profiles')
            ->where('entry_id', (string) $entry->id())
            ->where('site', (string) $entry->locale())
            ->update(['published' => false, 'hidden' => true, 'updated_at' => now()]);
    }

    private function coordinate(mixed $value, int $limit): ?float
    {
        if (! is_numeric($value) || abs((float) $value) > $limit) {
            return null;
        }

        return (float) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}

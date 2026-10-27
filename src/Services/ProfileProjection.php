<?php

namespace Goldnead\Directory\Services;

use Goldnead\Directory\Search\SearchEngine;
use Illuminate\Support\Facades\DB;
use Statamic\Contracts\Entries\Entry;

class ProfileProjection
{
    public function sync(Entry $entry): void
    {
        $collection = $entry->collectionHandle();

        if (! Collections::has($collection)) {
            $this->hide($entry);

            return;
        }

        $fields = Collections::fields($collection);
        $entryData = $entry->data()->all();
        $title = (string) ($entryData[$fields['title']] ?? $entry->get('title') ?? '');

        if ($title === '') {
            $this->hide($entry);

            return;
        }

        $filterValues = array_fill_keys(Collections::filterColumns(), null);
        foreach (Collections::filters($collection) as $column => $field) {
            $filterValues[$column] = $this->nullableString($entryData[$field] ?? null);
        }

        $identity = ['entry_id' => (string) $entry->id(), 'site' => (string) $entry->locale()];
        $projectionValues = [
            'collection' => $collection,
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

        $this->index($collection, $identity, $projectionValues);
    }

    public function forget(Entry $entry): void
    {
        $this->unindex($entry);

        DB::table('directory_profiles')
            ->where('entry_id', (string) $entry->id())
            ->where('site', (string) $entry->locale())
            ->delete();
    }

    private function hide(Entry $entry): void
    {
        $this->unindex($entry);

        DB::table('directory_profiles')
            ->where('entry_id', (string) $entry->id())
            ->where('site', (string) $entry->locale())
            ->update(['published' => false, 'hidden' => true, 'updated_at' => now()]);
    }

    /** Only public, published, visible profiles live in the index; everything else is removed. */
    private function index(string $collection, array $identity, array $values): void
    {
        $engine = $this->engine();

        if (! $engine->enabled()) {
            return;
        }

        if ($values['published'] && ! $values['hidden'] && $values['visibility'] === 'public') {
            $engine->upsert($collection, $identity['entry_id'], $identity['site'], ['title' => $values['title'], 'city' => $values['city']]);
        } else {
            $engine->delete($collection, $identity['entry_id'], $identity['site']);
        }
    }

    private function unindex(Entry $entry): void
    {
        $engine = $this->engine();

        if (! $engine->enabled()) {
            return;
        }

        // The projection row knows which collection the document was indexed under.
        $collections = DB::table('directory_profiles')
            ->where('entry_id', (string) $entry->id())->where('site', (string) $entry->locale())
            ->pluck('collection')->all();

        foreach ($collections as $collection) {
            $engine->delete((string) $collection, (string) $entry->id(), (string) $entry->locale());
        }
    }

    private function engine(): SearchEngine
    {
        return app(SearchEngine::class);
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

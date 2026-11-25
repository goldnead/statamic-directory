<?php

namespace Goldnead\Directory\Services;

use Goldnead\Directory\Search\SearchEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Entry;

/**
 * Rebuilds the disposable projection from the canonical Entries. Bindings (`team_id`,
 * `legacy_id`) are never touched; ProfileProjection::sync() only writes derived columns.
 */
class ProfileRebuild
{
    public function __construct(private ProfileProjection $projection) {}

    /**
     * @param  callable(int, int): void|null  $progress  receives processed and total
     * @return array{processed: int, pruned: int, errors: int}
     */
    public function run(?string $collection = null, ?string $entryId = null, bool $prune = false, ?callable $progress = null): array
    {
        $handles = $this->handles($collection);
        $entries = $this->entries($handles, $entryId);
        $total = count($entries);
        $processed = 0;
        $seen = [];
        $perCollection = [];
        $errors = 0;

        foreach ($handles as $handle) {
            app(SearchEngine::class)->configure($handle);
        }

        foreach ($entries as $entry) {
            // One broken entry (a value too long for its column, a bad field) must not
            // leave every entry after it unprojected. It is reported and counted, and
            // still counts as seen: pruning must not drop the row it already has.
            try {
                $this->projection->sync($entry);
            } catch (\Throwable $e) {
                report($e);
                Log::warning('directory rebuild: entry could not be projected', ['entry' => $entry->id(), 'site' => $entry->locale(), 'error' => $e->getMessage()]);
                $errors++;
            }

            $seen[$entry->id().'|'.$entry->locale()] = true;
            $perCollection[$entry->collectionHandle()] = ($perCollection[$entry->collectionHandle()] ?? 0) + 1;
            $processed++;

            if ($progress !== null && ($processed % 25 === 0 || $processed === $total)) {
                $progress($processed, $total);
            }
        }

        $pruned = $prune && $entryId === null ? $this->prune($handles, $seen, $perCollection) : 0;

        return ['processed' => $processed, 'pruned' => $pruned, 'errors' => $errors];
    }

    public function count(?string $collection = null, ?string $entryId = null): int
    {
        return count($this->entries($this->handles($collection), $entryId));
    }

    /** @return array<int, string> */
    private function handles(?string $collection): array
    {
        $handles = $collection !== null ? [$collection] : Collections::handles();

        return array_values(array_filter($handles, fn (string $handle) => Collections::has($handle)));
    }

    /** @return array<int, \Statamic\Contracts\Entries\Entry> */
    private function entries(array $handles, ?string $entryId): array
    {
        if ($handles === []) {
            return [];
        }

        $query = Entry::query()->whereIn('collection', $handles);

        if ($entryId !== null) {
            $query->where('id', $entryId);
        }

        return $query->get()->all();
    }

    /** Removes rows whose Entry no longer exists. The binding is lost with them, as on a normal delete. */
    private function prune(array $handles, array $seen, array $perCollection): int
    {
        $this->refuseEmptyPrune($handles, $perCollection);

        $stale = [];

        DB::table('directory_profiles')->whereIn('collection', $handles)
            ->select(['id', 'entry_id', 'site', 'collection'])
            ->orderBy('id')
            ->each(function ($row) use ($seen, &$stale): void {
                if (! isset($seen[$row->entry_id.'|'.$row->site])) {
                    $stale[] = $row->id;
                    app(SearchEngine::class)->delete($row->collection, $row->entry_id, $row->site);
                }
            });

        foreach (array_chunk($stale, 500) as $chunk) {
            DB::table('directory_profiles')->whereIn('id', $chunk)->delete();
        }

        return count($stale);
    }

    /**
     * No Entry at all for a collection that still has rows means the Entries are missing or unreadable,
     * not that every profile was deleted. Pruning then would drop their bindings (team_id, legacy_id).
     * Checked per collection: one healthy collection says nothing about another.
     *
     * @param  array<string, int>  $perCollection  entries seen, by collection handle
     */
    private function refuseEmptyPrune(array $handles, array $perCollection): void
    {
        $empty = array_values(array_filter($handles, fn (string $handle) => ($perCollection[$handle] ?? 0) === 0));

        if ($empty === []) {
            return;
        }

        $rows = DB::table('directory_profiles')->whereIn('collection', $empty)
            ->select('collection', DB::raw('count(*) as rows_count'))
            ->groupBy('collection')
            ->pluck('rows_count', 'collection');

        if ($rows->isEmpty()) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Refusing to prune: no entries found for collection(s) %s but %d profile rows exist.',
            $rows->keys()->implode(', '),
            $rows->sum(),
        ));
    }
}

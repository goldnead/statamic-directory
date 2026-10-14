<?php

namespace Goldnead\Directory\Services;

use Illuminate\Support\Facades\DB;
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
     * @return array{processed: int, pruned: int}
     */
    public function run(?string $collection = null, ?string $entryId = null, bool $prune = false, ?callable $progress = null): array
    {
        $handles = $this->handles($collection);
        $entries = $this->entries($handles, $entryId);
        $total = count($entries);
        $processed = 0;
        $seen = [];

        foreach ($entries as $entry) {
            $this->projection->sync($entry);
            $seen[$entry->id().'|'.$entry->locale()] = true;
            $processed++;

            if ($progress !== null && ($processed % 25 === 0 || $processed === $total)) {
                $progress($processed, $total);
            }
        }

        $pruned = $prune && $entryId === null ? $this->prune($handles, $seen) : 0;

        return ['processed' => $processed, 'pruned' => $pruned];
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
    private function prune(array $handles, array $seen): int
    {
        $stale = [];

        DB::table('directory_profiles')->whereIn('collection', $handles)
            ->select(['id', 'entry_id', 'site'])
            ->orderBy('id')
            ->each(function ($row) use ($seen, &$stale): void {
                if (! isset($seen[$row->entry_id.'|'.$row->site])) {
                    $stale[] = $row->id;
                }
            });

        foreach (array_chunk($stale, 500) as $chunk) {
            DB::table('directory_profiles')->whereIn('id', $chunk)->delete();
        }

        return count($stale);
    }
}

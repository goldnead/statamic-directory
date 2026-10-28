<?php

namespace Goldnead\Directory\Search;

/**
 * Text search backend. It only ever sees public fields and only ranks entry ids; what may be
 * shown is decided by the projection and re-checked against the Entry on output.
 */
interface SearchEngine
{
    public function enabled(): bool;

    /** Creates the index for a profile collection and applies its settings. */
    public function configure(string $collection): void;

    /** @param  array{title: string, city: ?string}  $document */
    public function upsert(string $collection, string $entryId, string $site, array $document): void;

    public function delete(string $collection, string $entryId, string $site): void;

    /**
     * @return list<string>|null entry ids by relevance, or null when the engine failed (caller falls back to SQL)
     */
    public function search(string $collection, string $site, string $query, int $limit): ?array;
}

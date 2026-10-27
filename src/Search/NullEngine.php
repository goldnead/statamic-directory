<?php

namespace Goldnead\Directory\Search;

/** Default: no search engine, ProfileSearch keeps using SQL. */
class NullEngine implements SearchEngine
{
    public function enabled(): bool
    {
        return false;
    }

    public function configure(string $collection): void {}

    public function upsert(string $collection, string $entryId, string $site, array $document): void {}

    public function delete(string $collection, string $entryId, string $site): void {}

    public function search(string $collection, string $site, string $query, int $limit): ?array
    {
        return null;
    }
}

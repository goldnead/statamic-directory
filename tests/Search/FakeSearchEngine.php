<?php

namespace Goldnead\Directory\Tests\Search;

use Goldnead\Directory\Search\SearchEngine;

/** In-memory engine with edit-distance tolerance, standing in for Meilisearch in tests. */
class FakeSearchEngine implements SearchEngine
{
    /** @var array<string, array<string, array{entry_id: string, site: string, text: string}>> */
    public array $documents = [];

    public bool $failing = false;

    public function enabled(): bool
    {
        return true;
    }

    public function configure(string $collection): void {}

    public function upsert(string $collection, string $entryId, string $site, array $document): void
    {
        $this->documents[$collection][$entryId.'|'.$site] = [
            'entry_id' => $entryId,
            'site' => $site,
            'text' => mb_strtolower($document['title'].' '.($document['city'] ?? '')),
        ];
    }

    public function delete(string $collection, string $entryId, string $site): void
    {
        unset($this->documents[$collection][$entryId.'|'.$site]);
    }

    public function search(string $collection, string $site, string $query, int $limit): ?array
    {
        if ($this->failing) {
            return null;
        }

        $query = mb_strtolower(trim($query));
        $hits = [];

        foreach ($this->documents[$collection] ?? [] as $doc) {
            if ($doc['site'] !== $site) {
                continue;
            }
            foreach (preg_split('/\s+/', $doc['text']) as $word) {
                $distance = levenshtein($query, $word);
                if ($distance <= 2) {
                    $hits[$doc['entry_id']] = min($hits[$doc['entry_id']] ?? 99, $distance);
                }
            }
        }

        asort($hits);

        return array_slice(array_keys($hits), 0, $limit);
    }
}

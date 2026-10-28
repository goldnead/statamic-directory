<?php

namespace Goldnead\Directory\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Meilisearch over its REST API (Laravel HTTP client, no extra package). One index per profile
 * collection, `{prefix}_{collection}`. Failures are logged and never break a save; search()
 * reports them as null so the caller can fall back to SQL.
 */
class MeilisearchEngine implements SearchEngine
{
    private const DOWN_KEY = 'directory.search.engine_down';

    private const DOWN_SECONDS = 60;

    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return true;
    }

    public function configure(string $collection): void
    {
        $this->send('configure', fn (PendingRequest $http) => $http->patch($this->index($collection).'/settings', [
            'searchableAttributes' => ['title', 'city'],
            'filterableAttributes' => ['site'],
            'synonyms' => $this->config['synonyms'] ?? [],
        ]));
    }

    public function upsert(string $collection, string $entryId, string $site, array $document): void
    {
        // Whitelist: nothing but these fields ever leaves the application.
        $payload = [[
            'id' => $this->documentId($entryId, $site),
            'entry_id' => $entryId,
            'site' => $site,
            'title' => $document['title'],
            'city' => $document['city'] ?? null,
        ]];

        $this->send('upsert', fn (PendingRequest $http) => $http->post($this->index($collection).'/documents?primaryKey=id', $payload));
    }

    public function delete(string $collection, string $entryId, string $site): void
    {
        $this->send('delete', fn (PendingRequest $http) => $http->delete($this->index($collection).'/documents/'.$this->documentId($entryId, $site)));
    }

    public function search(string $collection, string $site, string $query, int $limit): ?array
    {
        if ($this->isDown()) {
            return null;
        }

        try {
            $response = $this->http()->post($this->index($collection).'/search', [
                'q' => $query,
                'filter' => 'site = "'.addcslashes($site, '"\\').'"',
                'limit' => $limit,
                'attributesToRetrieve' => ['entry_id'],
            ]);

            if (! $response->successful()) {
                Log::warning('directory: Meilisearch search failed', ['status' => $response->status()]);

                return null;
            }

            $ids = array_values(array_map(
                fn (array $hit): string => (string) $hit['entry_id'],
                array_filter((array) $response->json('hits'), fn ($hit) => is_array($hit) && isset($hit['entry_id']))
            ));

            // Zero hits from an index that holds nothing yet (engine just switched on, rebuild
            // pending) is "not ready", not "no such choir": the caller falls back to SQL.
            return $ids === [] && $this->hasDocuments($collection) !== true ? null : $ids;
        } catch (Throwable $e) {
            $this->markDown();
            Log::warning('directory: Meilisearch unreachable, using SQL for '.self::DOWN_SECONDS.'s', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** True/false from the stats, null when unknown (never cached, caller falls back to SQL). */
    private function hasDocuments(string $collection): ?bool
    {
        $key = 'directory.search.has_documents.'.$collection;

        try {
            if (Cache::get($key) === true) {
                return true;
            }
        } catch (Throwable) {
            // Cache is an optimisation only.
        }

        $response = $this->http()->get($this->index($collection).'/stats');

        if (! $response->successful()) {
            return null;
        }

        $has = (int) $response->json('numberOfDocuments') > 0;

        if ($has) {
            try {
                Cache::put($key, true, self::DOWN_SECONDS);
            } catch (Throwable) {
            }
        }

        return $has;
    }

    private function isDown(): bool
    {
        try {
            return Cache::has(self::DOWN_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    private function markDown(): void
    {
        try {
            Cache::put(self::DOWN_KEY, true, self::DOWN_SECONDS);
        } catch (Throwable) {
        }
    }

    private function send(string $action, callable $request): void
    {
        try {
            $response = $request($this->http());

            if (! $response->successful() && $response->status() !== 404) {
                Log::warning("directory: Meilisearch {$action} failed", ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Log::warning("directory: Meilisearch {$action} failed", ['error' => $e->getMessage()]);
        }
    }

    private function http(): PendingRequest
    {
        $http = Http::baseUrl(rtrim((string) $this->config['host'], '/'))->acceptJson()->connectTimeout(1)->timeout((int) ($this->config['timeout'] ?? 3));

        return ($this->config['key'] ?? null) ? $http->withToken((string) $this->config['key']) : $http;
    }

    private function index(string $collection): string
    {
        return '/indexes/'.($this->config['prefix'] ?? 'directory').'_'.$collection;
    }

    /** Meilisearch ids allow only [a-zA-Z0-9_-]. */
    private function documentId(string $entryId, string $site): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $entryId.'_'.$site);
    }
}

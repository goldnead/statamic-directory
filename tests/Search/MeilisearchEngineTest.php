<?php

namespace Goldnead\Directory\Tests\Search;

use Goldnead\Directory\Search\MeilisearchEngine;
use Goldnead\Directory\Search\NullEngine;
use Goldnead\Directory\Search\SearchEngine;
use Goldnead\Directory\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MeilisearchEngineTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('directory.search.driver', 'meilisearch');
        $app['config']->set('directory.search.meilisearch.host', 'http://meili.test:7700');
        $app['config']->set('directory.search.meilisearch.key', 'secret');
        $app['config']->set('directory.search.meilisearch.prefix', 'dir');
    }

    public function test_driver_config_selects_the_engine(): void
    {
        $this->assertInstanceOf(MeilisearchEngine::class, app(SearchEngine::class));

        config(['directory.search.driver' => 'sql']);
        $this->app->forgetInstance(SearchEngine::class);
        $this->assertInstanceOf(NullEngine::class, app(SearchEngine::class));
    }

    public function test_meilisearch_without_host_is_treated_as_no_engine(): void
    {
        config(['directory.search.meilisearch.host' => null]);
        $this->app->forgetInstance(SearchEngine::class);

        $this->assertInstanceOf(NullEngine::class, app(SearchEngine::class));
    }

    public function test_search_returns_ranked_entry_ids_scoped_to_site(): void
    {
        Http::fake(['*' => Http::response(['hits' => [['entry_id' => 'b'], ['entry_id' => 'a']]])]);

        $ids = app(SearchEngine::class)->search('choirs', 'de', 'Kamerchor', 100);

        $this->assertSame(['b', 'a'], $ids);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://meili.test:7700/indexes/dir_choirs/search'
                && $request->hasHeader('Authorization', 'Bearer secret')
                && $request['q'] === 'Kamerchor'
                && $request['filter'] === 'site = "de"'
                && $request['limit'] === 100;
        });
    }

    public function test_search_failure_returns_null(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertNull(app(SearchEngine::class)->search('choirs', 'de', 'x', 10));
    }

    public function test_connection_failure_sets_a_down_marker_and_skips_the_engine_afterwards(): void
    {
        Cache::flush();
        $attempts = 0;
        Http::fake(['*' => function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('refused');
        }]);

        $engine = app(SearchEngine::class);
        $this->assertNull($engine->search('choirs', 'de', 'x', 10));
        $this->assertNull($engine->search('choirs', 'de', 'x', 10));

        $this->assertSame(1, $attempts);
    }

    public function test_requests_use_a_short_connect_timeout(): void
    {
        $method = new \ReflectionMethod(MeilisearchEngine::class, 'http');
        $options = $method->invoke(app(SearchEngine::class))->getOptions();

        $this->assertSame(1, $options['connect_timeout']);
        $this->assertSame(3, $options['timeout']);
    }

    public function test_empty_result_from_an_empty_index_counts_as_unavailable(): void
    {
        Cache::flush();
        Http::fake([
            '*/search' => Http::response(['hits' => []]),
            '*/stats' => Http::response(['numberOfDocuments' => 0]),
        ]);

        $this->assertNull(app(SearchEngine::class)->search('choirs', 'de', 'x', 10));
    }

    public function test_empty_result_from_a_filled_index_is_a_real_zero(): void
    {
        Cache::flush();
        Http::fake([
            '*/search' => Http::response(['hits' => []]),
            '*/stats' => Http::response(['numberOfDocuments' => 42]),
        ]);

        $this->assertSame([], app(SearchEngine::class)->search('choirs', 'de', 'x', 10));
    }

    public function test_stats_failure_falls_back_and_is_not_cached(): void
    {
        Cache::flush();
        $stats = 0;
        Http::fake([
            '*/search' => Http::response(['hits' => []]),
            '*/stats' => function () use (&$stats) {
                return ++$stats === 1 ? Http::response('boom', 500) : Http::response(['numberOfDocuments' => 42]);
            },
        ]);

        $engine = app(SearchEngine::class);
        $this->assertNull($engine->search('choirs', 'de', 'x', 10));
        $this->assertSame([], $engine->search('choirs', 'de', 'x', 10));
    }

    public function test_a_broken_cache_store_never_breaks_search(): void
    {
        Cache::shouldReceive('has')->andThrow(new \RuntimeException('cache down'));
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('cache down'));
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('cache down'));
        Cache::shouldReceive('forget')->andThrow(new \RuntimeException('cache down'));
        Http::fake(['*' => Http::response(['hits' => [['entry_id' => 'a']]])]);

        $this->assertSame(['a'], app(SearchEngine::class)->search('choirs', 'de', 'x', 10));

        Http::fake(['*' => fn () => throw new ConnectionException('refused')]);
        $this->assertNull(app(SearchEngine::class)->search('choirs', 'de', 'x', 10));
    }

    public function test_upsert_sends_only_public_fields(): void
    {
        Http::fake(['*' => Http::response(['taskUid' => 1], 202)]);

        app(SearchEngine::class)->upsert('choirs', 'e1', 'de', ['title' => 'Kammerchor', 'city' => 'Berlin', 'visibility' => 'private', 'team_id' => 5]);

        Http::assertSent(function (Request $request): bool {
            $doc = $request->data()[0] ?? [];

            return str_contains($request->url(), '/indexes/dir_choirs/documents?primaryKey=id')
                && array_keys($doc) === ['id', 'entry_id', 'site', 'title', 'city']
                && $doc['title'] === 'Kammerchor';
        });
    }

    public function test_upsert_failure_does_not_throw(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        app(SearchEngine::class)->upsert('choirs', 'e1', 'de', ['title' => 'T', 'city' => null]);
        $this->addToAssertionCount(1);
    }

    public function test_configure_sets_searchable_filterable_and_synonyms(): void
    {
        Http::fake(['*' => Http::response(['taskUid' => 1], 202)]);

        app(SearchEngine::class)->configure('choirs');

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/indexes/dir_choirs/settings')
                && $request->method() === 'PATCH'
                && $request['searchableAttributes'] === ['title', 'city']
                && $request['filterableAttributes'] === ['site']
                && isset($request['synonyms']['kammerchor']);
        });
    }
}

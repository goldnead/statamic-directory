<?php

namespace Goldnead\Directory\Tests\Search;

use Goldnead\Directory\Search\MeilisearchEngine;
use Goldnead\Directory\Search\NullEngine;
use Goldnead\Directory\Search\SearchEngine;
use Goldnead\Directory\Tests\TestCase;
use Illuminate\Http\Client\Request;
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

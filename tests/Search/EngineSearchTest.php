<?php

namespace Goldnead\Directory\Tests\Search;

use Goldnead\Directory\Search\NullEngine;
use Goldnead\Directory\Search\SearchEngine;
use Goldnead\Directory\Services\ProfileProjection;
use Goldnead\Directory\Services\ProfileSearch;
use Goldnead\Directory\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Statamic\Facades\Entry;

class EngineSearchTest extends TestCase
{
    private FakeSearchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
            });
        }
        $this->artisan('migrate', ['--path' => __DIR__.'/../../database/migrations', '--realpath' => true]);

        $this->engine = new FakeSearchEngine;
        $this->app->instance(SearchEngine::class, $this->engine);
    }

    public function test_a_typo_finds_the_profile_through_the_engine(): void
    {
        $this->indexed('k', 'Kammerchor Berlin');
        $this->indexed('o', 'Orchester Hamburg');

        $this->assertSame(['k'], array_column($this->search(['q' => 'Kamerchor'])['data'], 'id'));
    }

    public function test_invisible_entries_are_never_returned_even_if_the_index_holds_them(): void
    {
        $this->indexed('ok', 'Kammerchor Berlin');
        $this->indexed('priv', 'Kammerchor Privat', ['visibility' => 'private'], ['visibility' => 'private']);
        $this->indexed('hid', 'Kammerchor Hidden', ['hidden' => true], ['hidden' => true]);
        // stale projection: row still says public, the Entry has turned private
        $this->indexed('stale', 'Kammerchor Stale', ['visibility' => 'private']);

        $this->assertSame(['ok'], array_column($this->search(['q' => 'Kamerchor'])['data'], 'id'));
    }

    public function test_projection_sync_only_indexes_public_profiles(): void
    {
        $projection = app(ProfileProjection::class);
        $projection->sync($this->entry('pub', ['title' => 'Kammerchor', 'visibility' => 'public']));
        $projection->sync($this->entry('priv', ['title' => 'Kammerchor Privat', 'visibility' => 'private']));
        $projection->sync($this->entry('hid', ['title' => 'Kammerchor Hidden', 'visibility' => 'public', 'hidden' => true]));

        $this->assertSame(['pub|en'], array_keys($this->engine->documents['choirs'] ?? []));

        // goes private later: removed from the index
        $projection->sync($this->entry('pub', ['title' => 'Kammerchor', 'visibility' => 'private']));
        $this->assertSame([], $this->engine->documents['choirs'] ?? []);
    }

    public function test_forget_removes_the_document(): void
    {
        $projection = app(ProfileProjection::class);
        $entry = $this->entry('pub', ['title' => 'Kammerchor', 'visibility' => 'public']);
        $projection->sync($entry);
        $projection->forget($entry);

        $this->assertSame([], $this->engine->documents['choirs'] ?? []);
    }

    public function test_engine_failure_falls_back_to_sql(): void
    {
        $this->indexed('k', 'Kammerchor Berlin');
        $this->engine->failing = true;

        $this->assertSame(['k'], array_column($this->search(['q' => 'Kammer'])['data'], 'id'));
    }

    public function test_no_engine_keeps_the_sql_behaviour(): void
    {
        $this->app->instance(SearchEngine::class, new NullEngine);
        $this->indexed('k', 'Kammerchor Berlin', [], [], false);

        $this->assertSame(['k'], array_column($this->search(['q' => 'Kammer'])['data'], 'id'));
        $this->assertSame([], $this->search(['q' => 'Kamerchor'])['data']);
    }

    public function test_filters_still_apply_to_engine_hits(): void
    {
        $this->indexed('p', 'Kammerchor Projekt', [], ['choir_type' => 'project']);
        $this->indexed('r', 'Kammerchor Regulär', [], ['choir_type' => 'regular']);

        $this->assertSame(['p'], array_column($this->search(['q' => 'Kamerchor', 'choir_type' => 'project'])['data'], 'id'));
    }

    private function search(array $params): array
    {
        return app(ProfileSearch::class)->search($params, 'en');
    }

    /** Inserts a projection row, mocks its Entry and puts it in the fake index. */
    private function indexed(string $id, string $title, array $entryData = [], array $row = [], bool $index = true): void
    {
        $data = $entryData + ['title' => $title, 'visibility' => 'public', 'hidden' => false];

        \DB::table('directory_profiles')->insert($row + [
            'entry_id' => $id, 'site' => 'en', 'title' => $title, 'collection' => 'choirs',
            'visibility' => 'public', 'hidden' => false, 'published' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $entry = Mockery::mock(\Statamic\Entries\Entry::class);
        $entry->shouldReceive('in')->andReturnSelf();
        $entry->shouldReceive('id')->andReturn($id);
        $entry->shouldReceive('locale')->andReturn('en');
        $entry->shouldReceive('collectionHandle')->andReturn('choirs');
        $entry->shouldReceive('published')->andReturn(true);
        $entry->shouldReceive('data')->andReturn(collect($data));
        $entry->shouldReceive('get')->with('title')->andReturn($title);
        Entry::shouldReceive('find')->with($id)->andReturn($entry);

        if ($index) {
            $this->engine->upsert('choirs', $id, 'en', ['title' => $title, 'city' => null]);
        }
    }

    private function entry(string $id, array $data)
    {
        $entry = Mockery::mock(\Statamic\Contracts\Entries\Entry::class);
        $entry->shouldReceive('collectionHandle')->andReturn('choirs');
        $entry->shouldReceive('id')->andReturn($id);
        $entry->shouldReceive('locale')->andReturn('en');
        $entry->shouldReceive('data')->andReturn(collect($data));
        $entry->shouldReceive('get')->with('title')->andReturn($data['title']);
        $entry->shouldReceive('published')->andReturn(true);

        return $entry;
    }
}

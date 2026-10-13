<?php

namespace Goldnead\Directory\Tests;

use Goldnead\Directory\Services\Collections;
use Goldnead\Directory\Services\ProfileProjection;
use Goldnead\Directory\Services\ProfileSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Statamic\Facades\Entry;

class MultiCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
            });
        }

        config(['directory.collections' => [
            'choirs' => ['filters' => ['choir_type' => 'choir_type']],
            'leaders' => [
                'fields' => ['title' => 'name', 'city' => 'town'],
                'filters' => ['focus' => 'focuses'],
            ],
        ]]);

        $this->artisan('migrate', ['--path' => __DIR__.'/../database/migrations', '--realpath' => true]);
    }

    public function test_search_is_scoped_per_type_and_defaults_to_the_first_collection(): void
    {
        $this->sync('c1', 'choirs', ['title' => 'Chor Eins']);
        $this->sync('l1', 'leaders', ['name' => 'Leiter Eins']);

        $this->assertSame(['c1'], $this->ids([]));
        $this->assertSame(['c1'], $this->ids(['type' => 'choirs']));
        $this->assertSame(['l1'], $this->ids(['type' => 'leaders']));
        $this->assertSame('Leiter Eins', $this->search(['type' => 'leaders'])['data'][0]['title']);
        $this->assertSame('leaders', $this->search(['type' => 'leaders'])['data'][0]['type']);
    }

    public function test_filters_apply_per_type(): void
    {
        $this->sync('c1', 'choirs', ['title' => 'A', 'choir_type' => 'mixed']);
        $this->sync('c2', 'choirs', ['title' => 'B', 'choir_type' => 'men']);
        $this->sync('l1', 'leaders', ['name' => 'L1', 'focuses' => 'jazz']);
        $this->sync('l2', 'leaders', ['name' => 'L2', 'focuses' => 'pop']);

        $this->assertSame(['c1'], $this->ids(['choir_type' => 'mixed']));
        $this->assertSame(['l2'], $this->ids(['type' => 'leaders', 'focus' => 'pop']));
        // a filter of another collection is ignored
        $this->assertSame(['l1', 'l2'], $this->ids(['type' => 'leaders', 'choir_type' => 'men']));
    }

    public function test_private_and_hidden_are_excluded_per_type(): void
    {
        $this->sync('l1', 'leaders', ['name' => 'Open']);
        $this->sync('l2', 'leaders', ['name' => 'Private', 'visibility' => 'private']);
        $this->sync('l3', 'leaders', ['name' => 'Hidden', 'hidden' => true]);
        $this->sync('c2', 'choirs', ['title' => 'Private', 'visibility' => 'private']);

        $this->assertSame(['l1'], $this->ids(['type' => 'leaders']));
        $this->assertSame([], $this->ids(['type' => 'choirs']));
    }

    public function test_unknown_type_is_rejected_and_other_collections_stay_out(): void
    {
        $this->assertTrue(Validator::make(['type' => 'nope'], ProfileSearch::rules())->fails());
        $this->assertFalse(Validator::make(['type' => 'leaders'], ProfileSearch::rules())->fails());

        $this->sync('x1', 'pages', ['title' => 'Page']);
        $this->assertDatabaseMissing('directory_profiles', ['entry_id' => 'x1', 'hidden' => false]);
    }

    public function test_legacy_single_collection_config_still_works(): void
    {
        config(['directory.collections' => [], 'directory.collection' => 'choirs', 'directory.filters' => ['choir_type' => 'choir_type']]);

        $this->sync('c1', 'choirs', ['title' => 'Chor']);
        $this->sync('l1', 'leaders', ['name' => 'Leiter']);

        $this->assertSame(['c1'], $this->ids([]));
        $this->assertSame(['choirs'], Collections::handles());
        $this->assertDatabaseMissing('directory_profiles', ['entry_id' => 'l1', 'published' => true]);
    }

    private function sync(string $id, string $collection, array $data): void
    {
        $data += ['visibility' => 'public', 'hidden' => false];

        $entry = Mockery::mock(\Statamic\Entries\Entry::class);
        $entry->shouldReceive('in')->andReturnSelf();
        $entry->shouldReceive('id')->andReturn($id);
        $entry->shouldReceive('locale')->andReturn('en');
        $entry->shouldReceive('collectionHandle')->andReturn($collection);
        $entry->shouldReceive('published')->andReturn(true);
        $entry->shouldReceive('data')->andReturn(collect($data));
        $entry->shouldReceive('get')->with('title')->andReturn($data['title'] ?? null);
        Entry::shouldReceive('find')->with($id)->andReturn($entry);

        app(ProfileProjection::class)->sync($entry);
    }

    private function search(array $params): array
    {
        return app(ProfileSearch::class)->search($params, 'en');
    }

    private function ids(array $params): array
    {
        return array_column($this->search($params)['data'], 'id');
    }
}

<?php

namespace Goldnead\Directory\Tests;

use Goldnead\Directory\Services\ProfileSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Statamic\Facades\Entry;

class ProfileSearchTest extends TestCase
{
    // Berlin Mitte as origin
    private const ORIGIN = ['lat' => 52.52, 'lng' => 13.405];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
            });
        }
        $this->artisan('migrate', ['--path' => __DIR__.'/../database/migrations', '--realpath' => true]);
    }

    public function test_radius_includes_nearby_and_excludes_distant_profiles(): void
    {
        $this->profile('potsdam', 'Potsdam Choir', 52.39, 13.06);   // ~27 km
        $this->profile('hamburg', 'Hamburg Choir', 53.55, 9.99);    // ~255 km

        $result = $this->search(self::ORIGIN + ['radius_km' => 50]);

        $this->assertSame(['potsdam'], array_column($result['data'], 'id'));
        $this->assertEqualsWithDelta(27, $result['data'][0]['distance_km'], 2);
    }

    public function test_private_profiles_never_appear(): void
    {
        $this->profile('secret', 'Secret Choir', 52.52, 13.405, ['visibility' => 'private']);

        $this->assertSame([], $this->search(self::ORIGIN + ['radius_km' => 10])['data']);
        $this->assertSame([], $this->search([])['data']);
    }

    public function test_hidden_profiles_never_appear(): void
    {
        $this->profile('gone', 'Hidden Choir', 52.52, 13.405, ['hidden' => true]);

        $this->assertSame([], $this->search(self::ORIGIN + ['radius_km' => 10])['data']);
    }

    public function test_second_page_returns_the_remaining_profiles(): void
    {
        foreach (['a', 'b', 'c'] as $i => $id) {
            $this->profile($id, "Choir $id", 52.52 + $i * 0.01, 13.405);
        }

        $page2 = $this->search(['page' => 2, 'per_page' => 2]);
        $this->assertSame(['c'], array_column($page2['data'], 'id'));
        $this->assertSame(['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $page2['meta']);

        $geoPage2 = $this->search(self::ORIGIN + ['radius_km' => 50, 'page' => 2, 'per_page' => 2]);
        $this->assertSame(['c'], array_column($geoPage2['data'], 'id'));
    }

    public function test_results_are_sorted_by_distance_when_a_location_is_given(): void
    {
        $this->profile('far', 'Aaa Far', 52.80, 13.405);
        $this->profile('near', 'Zzz Near', 52.53, 13.405);
        $this->profile('mid', 'Mmm Mid', 52.62, 13.405);

        $this->assertSame(['near', 'mid', 'far'], array_column($this->search(self::ORIGIN)['data'], 'id'));
        $this->assertSame(['far', 'mid', 'near'], array_column($this->search([])['data'], 'id'));
    }

    public function test_filters_and_per_page_cap(): void
    {
        $this->profile('p', 'Project Choir', 52.5, 13.4, ['choir_type' => 'project']);
        $this->profile('r', 'Regular Choir', 52.5, 13.4, ['choir_type' => 'regular']);

        $this->assertSame(['p'], array_column($this->search(['choir_type' => 'project'])['data'], 'id'));

        $validator = validator(['per_page' => 51], ProfileSearch::rules());
        $this->assertTrue($validator->fails());
    }

    public function test_projection_stores_coordinates_and_filters_and_rejects_out_of_range(): void
    {
        $make = function (string $id, array $data) {
            $entry = Mockery::mock(\Statamic\Contracts\Entries\Entry::class);
            $entry->shouldReceive('collectionHandle')->andReturn('choirs');
            $entry->shouldReceive('id')->andReturn($id);
            $entry->shouldReceive('locale')->andReturn('en');
            $entry->shouldReceive('data')->andReturn(collect($data));
            $entry->shouldReceive('get')->with('title')->andReturn('T');
            $entry->shouldReceive('published')->andReturn(true);

            return $entry;
        };
        $projection = app(\Goldnead\Directory\Services\ProfileProjection::class);
        $projection->sync($make('ok', ['title' => 'T', 'lat' => '52.5', 'lng' => 13.4, 'choir_type' => 'project']));
        $projection->sync($make('bad', ['title' => 'T', 'lat' => 952, 'lng' => 'x']));

        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'ok', 'lat' => 52.5, 'lng' => 13.4, 'choir_type' => 'project']);
        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'bad', 'lat' => null, 'lng' => null]);
    }

    private function search(array $params): array
    {
        return app(ProfileSearch::class)->search($params, 'en');
    }

    private function profile(string $id, string $title, float $lat, float $lng, array $extra = []): void
    {
        $data = $extra + ['title' => $title, 'visibility' => 'public', 'hidden' => false];

        \DB::table('directory_profiles')->insert([
            'entry_id' => $id, 'site' => 'en', 'title' => $title, 'lat' => $lat, 'lng' => $lng,
            'visibility' => $data['visibility'], 'hidden' => $data['hidden'], 'published' => true,
            'choir_type' => $data['choir_type'] ?? null,
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
    }
}

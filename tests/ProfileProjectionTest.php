<?php

namespace Goldnead\Directory\Tests;

use Goldnead\Directory\Services\ProfileProjection;
use Mockery;
use Statamic\Contracts\Entries\Entry;

class ProfileProjectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => __DIR__.'/../database/migrations',
            '--realpath' => true,
        ]);
        $this->assertSame('choirs', config('directory.collection'));
        $this->assertSame('hidden', config('directory.fields.hidden'));
    }

    public function test_repeated_sync_updates_the_same_site_scoped_projection(): void
    {
        $entry = $this->entry('choir-1', 'en', [
            'title' => 'North Choir',
            'city' => 'Berlin',
            'description' => 'A public choir',
            'visibility' => 'public',
            'hidden' => false,
            'email' => 'private@example.test',
        ]);
        $projection = app(ProfileProjection::class);

        $projection->sync($entry);
        $projection->sync($entry);

        $this->assertDatabaseCount('directory_profiles', 1);
        $this->assertDatabaseHas('directory_profiles', [
            'entry_id' => 'choir-1',
            'site' => 'en',
            'title' => 'North Choir',
            'visibility' => 'public',
        ]);
        $this->assertArrayNotHasKey('email', (array) \DB::table('directory_profiles')->first());
    }

    public function test_sync_keeps_localized_profiles_separate_and_delete_is_scoped(): void
    {
        $projection = app(ProfileProjection::class);
        $projection->sync($this->entry('choir-1', 'en', ['title' => 'North Choir', 'visibility' => 'public']));
        $projection->sync($this->entry('choir-1', 'de', ['title' => 'Nordchor', 'visibility' => 'public']));

        $this->assertDatabaseCount('directory_profiles', 2);

        $projection->forget($this->entry('choir-1', 'en', []));

        $this->assertDatabaseCount('directory_profiles', 1);
        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'choir-1', 'site' => 'de']);
    }

    public function test_sync_preserves_trusted_bindings_and_hides_a_profile_with_no_title(): void
    {
        $projection = app(ProfileProjection::class);
        $entry = $this->entry('choir-1', 'en', ['title' => 'North Choir', 'visibility' => 'public']);
        $projection->sync($entry);

        \DB::table('directory_profiles')->where('entry_id', 'choir-1')->update([
            'team_id' => 72,
            'legacy_id' => 'legacy-cuid-1',
        ]);

        $projection->sync($this->entry('choir-1', 'en', ['title' => '', 'visibility' => 'public']));

        $this->assertDatabaseHas('directory_profiles', [
            'entry_id' => 'choir-1',
            'team_id' => 72,
            'legacy_id' => 'legacy-cuid-1',
            'published' => false,
            'hidden' => true,
        ]);
    }

    private function entry(string $id, string $site, array $data): Entry
    {
        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('collectionHandle')->andReturn('choirs');
        $entry->shouldReceive('id')->andReturn($id);
        $entry->shouldReceive('locale')->andReturn($site);
        $entry->shouldReceive('data')->andReturn(collect($data));
        $entry->shouldReceive('get')->with('title')->andReturn($data['title'] ?? null);
        $entry->shouldReceive('published')->andReturn(true);

        return $entry;
    }
}

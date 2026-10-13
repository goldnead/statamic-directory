<?php

namespace Goldnead\Directory\Tests;

use Goldnead\Directory\Services\ProfileProjection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Statamic\Contracts\Entries\Entry;

class ProfileProjectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
            });
        }
        Schema::enableForeignKeyConstraints();
        \DB::table('teams')->insert(['id' => 72]);

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

    public function test_trusted_bindings_are_unique_per_site(): void
    {
        $this->insertBoundProfile('choir-en', 'en', 'legacy-1', 72);
        $this->insertBoundProfile('choir-de', 'de', 'legacy-1', 72);

        $this->assertDatabaseCount('directory_profiles', 2);

        $this->expectException(QueryException::class);
        $this->insertBoundProfile('another-en', 'en', 'legacy-1', 72);
    }

    public function test_deleting_a_team_clears_its_directory_binding(): void
    {
        $this->insertBoundProfile('choir-en', 'en', 'legacy-1', 72);

        \DB::table('teams')->where('id', 72)->delete();

        $this->assertDatabaseHas('directory_profiles', [
            'entry_id' => 'choir-en',
            'site' => 'en',
            'team_id' => null,
        ]);
    }

    public function test_foreign_key_migration_clears_orphans_and_keeps_valid_bindings(): void
    {
        $migration = require __DIR__.'/../database/migrations/2026_10_13_000000_add_team_foreign_key_to_directory_profiles.php';
        $migration->down();

        $this->insertBoundProfile('valid-profile', 'en', 'valid-legacy', 72);
        $this->insertBoundProfile('orphan-profile', 'en', 'orphan-legacy', 999);

        $migration->up();

        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'valid-profile', 'team_id' => 72]);
        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'orphan-profile', 'team_id' => null]);

        \DB::table('teams')->where('id', 72)->delete();

        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'valid-profile', 'team_id' => null]);
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

    private function insertBoundProfile(string $entryId, string $site, string $legacyId, int $teamId): void
    {
        \DB::table('directory_profiles')->insert([
            'entry_id' => $entryId,
            'site' => $site,
            'legacy_id' => $legacyId,
            'team_id' => $teamId,
            'title' => 'Choir',
            'visibility' => 'private',
            'hidden' => true,
            'published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

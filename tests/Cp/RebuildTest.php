<?php

namespace Goldnead\Directory\Tests\Cp;

use Goldnead\Directory\Jobs\RebuildProjection;
use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Support\RebuildStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class RebuildTest extends CpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Collection::make('choirs')->save();
        Entry::make()->id('e1')->collection('choirs')->data(['title' => 'Eins', 'city' => 'Bonn'])->save();
        Entry::make()->id('e2')->collection('choirs')->data(['title' => 'Zwei', 'city' => 'Ulm'])->save();
    }

    public function test_command_rebuilds_all_rows_and_keeps_the_binding(): void
    {
        DirectoryProfile::query()->where('entry_id', 'e1')->update(['team_id' => 5, 'title' => 'stale']);
        DirectoryProfile::query()->where('entry_id', 'e2')->delete();

        $this->artisan('directory:rebuild')->assertSuccessful();

        $this->assertSame('Eins', DirectoryProfile::query()->where('entry_id', 'e1')->value('title'));
        $this->assertSame(5, DirectoryProfile::query()->where('entry_id', 'e1')->value('team_id'));
        $this->assertSame('Zwei', DirectoryProfile::query()->where('entry_id', 'e2')->value('title'));
    }

    public function test_command_rebuilds_one_entry_and_rejects_unknown_collections(): void
    {
        DirectoryProfile::query()->update(['title' => 'stale']);

        $this->artisan('directory:rebuild', ['--entry' => 'e2'])->assertSuccessful();

        $this->assertSame('Zwei', DirectoryProfile::query()->where('entry_id', 'e2')->value('title'));
        $this->assertSame('stale', DirectoryProfile::query()->where('entry_id', 'e1')->value('title'));

        $this->artisan('directory:rebuild', ['--collection' => 'nope'])->assertFailed();
    }

    public function test_prune_removes_rows_without_an_entry(): void
    {
        $this->profile(['entry_id' => 'ghost', 'collection' => 'choirs']);

        $this->artisan('directory:rebuild')->assertSuccessful();
        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'ghost']);

        $this->artisan('directory:rebuild', ['--prune' => true])->assertSuccessful();
        $this->assertDatabaseMissing('directory_profiles', ['entry_id' => 'ghost']);
    }

    public function test_cp_queues_a_full_rebuild(): void
    {
        Bus::fake();
        $this->actingAs($this->manager());

        $this->post('/cp/directory/rebuild')->assertRedirect();

        Bus::assertDispatched(RebuildProjection::class, fn ($job) => $job->collection === null && $job->entryId === null);
        $this->assertSame('queued', RebuildStatus::get()['state']);
    }

    public function test_cp_does_not_queue_a_second_rebuild_while_one_runs(): void
    {
        Bus::fake();
        RebuildStatus::put('running', ['processed' => 1, 'total' => 9]);
        $this->actingAs($this->manager());

        $this->post('/cp/directory/rebuild')->assertRedirect();

        Bus::assertNotDispatched(RebuildProjection::class);
    }

    public function test_job_rebuilds_and_reports_status(): void
    {
        DB::table('directory_profiles')->delete();
        $this->actingAs($this->manager());

        $this->post('/cp/directory/rebuild')->assertRedirect();

        $this->assertSame(2, DirectoryProfile::query()->count());
        $status = RebuildStatus::get();
        $this->assertSame('done', $status['state']);
        $this->assertSame(2, $status['processed']);
        $this->getJson('/cp/directory/rebuild-status')->assertOk()->assertJsonPath('rebuild.state', 'done');
    }

    public function test_single_profile_rebuild_from_the_cp(): void
    {
        DirectoryProfile::query()->update(['title' => 'stale']);
        $id = DirectoryProfile::query()->where('entry_id', 'e1')->value('id');
        $this->actingAs($this->manager());

        $this->post("/cp/directory/profiles/{$id}/rebuild")->assertRedirect();

        $this->assertSame('Eins', DirectoryProfile::query()->find($id)->title);
        $this->assertSame('stale', DirectoryProfile::query()->where('entry_id', 'e2')->value('title'));
        $this->assertSame('entry', RebuildStatus::get()['scope']);
    }
}

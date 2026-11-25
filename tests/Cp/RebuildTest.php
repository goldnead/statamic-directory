<?php

namespace Goldnead\Directory\Tests\Cp;

use Goldnead\Directory\Jobs\RebuildProjection;
use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Services\ProfileRebuild;
use Goldnead\Directory\Support\RebuildStatus;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\NullQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Throwable;

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

    /** Deleting the entries drops their rows through the normal delete path; the ghost row has no entry and stays. */
    private function choirsWithoutEntriesButAGhostRow(): void
    {
        Entry::find('e1')->delete();
        Entry::find('e2')->delete();
        $this->profile(['entry_id' => 'ghost', 'collection' => 'choirs']);
        $this->assertSame(1, DirectoryProfile::query()->count());
    }

    public function test_prune_refuses_when_a_collection_has_no_entries_left(): void
    {
        $this->choirsWithoutEntriesButAGhostRow();

        $this->artisan('directory:rebuild', ['--prune' => true])->assertFailed();

        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'ghost']);
    }

    public function test_prune_refuses_for_an_empty_collection_even_when_another_one_has_entries(): void
    {
        config(['directory.collections' => ['choirs' => [], 'leaders' => []]]);
        Collection::make('leaders')->save();
        $this->profile(['entry_id' => 'l-ghost', 'collection' => 'leaders']);

        $this->artisan('directory:rebuild', ['--prune' => true])->assertFailed();

        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'l-ghost']);
        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'e1']);
    }

    public function test_prune_with_an_entry_filter_never_deletes(): void
    {
        $this->choirsWithoutEntriesButAGhostRow();
        Entry::make()->id('e3')->collection('choirs')->data(['title' => 'Drei'])->save();

        $this->artisan('directory:rebuild', ['--entry' => 'e3', '--prune' => true])->assertSuccessful();

        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'ghost']);
    }

    public function test_failed_prune_job_records_the_reason_in_the_status(): void
    {
        $this->choirsWithoutEntriesButAGhostRow();

        $job = new RebuildProjection(null, null, true);
        try {
            $job->handle(app(ProfileRebuild::class));
        } catch (Throwable $e) {
            $job->failed($e);
        }

        $this->assertSame('failed', RebuildStatus::get()['state']);
        $this->assertStringContainsString('Refusing to prune', RebuildStatus::get()['error']);
        $this->assertDatabaseHas('directory_profiles', ['entry_id' => 'ghost']);
    }

    public function test_running_status_older_than_fifteen_minutes_counts_as_orphaned(): void
    {
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());

        Carbon::setTestNow('2026-11-25 10:00:00');
        RebuildStatus::put('running', ['processed' => 1, 'total' => 9]);

        Carbon::setTestNow('2026-11-25 10:14:59');
        $this->assertTrue(RebuildStatus::running());

        Carbon::setTestNow('2026-11-25 10:16:00');
        $this->assertFalse(RebuildStatus::running());
    }

    public function test_cp_queues_again_when_the_last_rebuild_is_orphaned(): void
    {
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());
        Bus::fake();

        Carbon::setTestNow('2026-11-25 10:00:00');
        RebuildStatus::put('queued', ['scope' => 'all']);

        Carbon::setTestNow('2026-11-25 10:20:00');
        $this->actingAs($this->manager());
        $this->post('/cp/directory/rebuild')->assertRedirect();

        Bus::assertDispatched(RebuildProjection::class);
    }

    public function test_cp_marks_the_status_failed_when_dispatch_fails(): void
    {
        $this->actingAs($this->manager());
        $this->breakQueue();

        $this->post('/cp/directory/rebuild')->assertRedirect();

        $this->assertSame('failed', RebuildStatus::get()['state']);
        $this->assertSame('queue down', RebuildStatus::get()['error']);
    }

    public function test_queue_option_fails_when_dispatch_fails(): void
    {
        $this->breakQueue();

        $this->artisan('directory:rebuild', ['--queue' => true])->assertFailed();

        $this->assertSame('failed', RebuildStatus::get()['state']);
        $this->assertSame('queue down', RebuildStatus::get()['error']);
    }

    private function breakQueue(): void
    {
        Queue::extend('broken', fn () => new class implements ConnectorInterface
        {
            public function connect(array $config)
            {
                return new class extends NullQueue
                {
                    public function push($job, $data = '', $queue = null)
                    {
                        throw new \RuntimeException('queue down');
                    }
                };
            }
        });
        config(['queue.default' => 'broken', 'queue.connections.broken' => ['driver' => 'broken']]);
    }
}

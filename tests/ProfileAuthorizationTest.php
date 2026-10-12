<?php

namespace Goldnead\Directory\Tests;

use Goldnead\Directory\Http\Controllers\ProfileController;
use Goldnead\Teams\Facades\Teams;
use Goldnead\Teams\Models\Team;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProfileAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => __DIR__.'/../database/migrations',
            '--realpath' => true,
        ]);
    }

    public function test_authorized_team_owner_can_edit_a_bound_profile(): void
    {
        $this->bindProfile();
        $this->mockCanonicalEntry('Edited Choir');
        $actor = new GenericUser(['id' => 'owner']);
        $team = Mockery::mock(Team::class);
        Teams::shouldReceive('find')->with(4)->andReturn($team);
        Teams::shouldReceive('can')->with($actor, $team, 'update team')->andReturn(true);

        $response = $this->update($actor, ['title' => 'Edited Choir']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_outsider_cannot_edit_profile(): void
    {
        $this->assertDenied('outsider');
    }

    public function test_member_without_permission_cannot_edit_profile(): void
    {
        $this->assertDenied('member');
    }

    public function test_authorized_editor_cannot_change_protected_profile_fields(): void
    {
        $this->bindProfile();
        $this->mockCanonicalEntry();
        $actor = new GenericUser(['id' => 'owner']);
        $team = Mockery::mock(Team::class);
        Teams::shouldReceive('find')->with(4)->andReturn($team);
        Teams::shouldReceive('can')->with($actor, $team, 'update team')->andReturn(true);

        $response = $this->update($actor, ['team_id' => 99]);

        $this->assertSame(422, $response->getStatusCode());
    }

    private function bindProfile(): void
    {
        \DB::table('directory_profiles')->insert([
            'entry_id' => 'profile-1',
            'site' => 'en',
            'team_id' => 4,
            'title' => 'Choir',
            'city' => 'Berlin',
            'visibility' => 'public',
            'published' => true,
            'hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertDenied(string $id): void
    {
        $this->bindProfile();
        $this->mockCanonicalEntry();
        $actor = new GenericUser(['id' => $id]);
        $team = Mockery::mock(Team::class);
        Teams::shouldReceive('find')->with(4)->andReturn($team);
        Teams::shouldReceive('can')->with($actor, $team, 'update team')->andReturn(false);

        try {
            $this->update($actor, ['title' => 'Unauthorized edit']);
            $this->fail('An actor without update team permission must be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function mockCanonicalEntry(?string $expectedTitle = null): void
    {
        $currentSite = Mockery::mock();
        $currentSite->shouldReceive('handle')->andReturn('en');
        Site::shouldReceive('current')->andReturn($currentSite);
        Site::shouldReceive('get')->with('en')->andReturn((object) ['handle' => 'en']);

        $entry = Mockery::mock(EntryContract::class);
        $entry->shouldReceive('in')->with('en')->andReturnSelf();
        $entry->shouldReceive('collectionHandle')->andReturn('choirs');
        $entry->shouldReceive('id')->andReturn('profile-1');
        $entry->shouldReceive('locale')->andReturn('en');
        $entry->shouldReceive('published')->andReturn(true);
        $entry->shouldReceive('data')->andReturn(collect([
            'visibility' => 'public',
            'hidden' => false,
        ]));
        $entry->shouldReceive('get')->with('title')->andReturn($expectedTitle ?? 'Choir');
        if ($expectedTitle) {
            $entry->shouldReceive('set')->with('title', $expectedTitle)->once();
            $entry->shouldReceive('save')->once();
        } else {
            $entry->shouldNotReceive('set');
            $entry->shouldNotReceive('save');
        }
        Entry::shouldReceive('find')->with('profile-1')->andReturn($entry);
    }

    private function update(GenericUser $actor, array $data): JsonResponse
    {
        $request = Request::create('/directory/profiles/profile-1?site=en', 'PATCH', $data);
        $request->setUserResolver(fn () => $actor);

        return app(ProfileController::class)->update($request, 'profile-1');
    }
}

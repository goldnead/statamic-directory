<?php

namespace Goldnead\Directory\Tests\Cp;

use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Http\Middleware\CP\HandleInertiaRequests;

abstract class CpTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
            });
        }

        $this->artisan('migrate', ['--path' => __DIR__.'/../../database/migrations', '--realpath' => true]);

        $this->withoutVite();
        Inertia::setRootView(HandleInertiaRequests::ROOT_VIEW);
        Cache::flush();
    }

    protected function userWith(array $permissions): \Statamic\Contracts\Auth\User
    {
        $handle = 'role_'.md5(implode(',', $permissions));
        Role::make($handle)->permissions(array_merge(['access cp'], $permissions))->save();

        $user = User::make()->email($handle.'@example.com')->assignRole($handle);
        $user->save();

        return $user;
    }

    protected function viewer(): \Statamic\Contracts\Auth\User
    {
        return $this->userWith(['view directory']);
    }

    protected function manager(): \Statamic\Contracts\Auth\User
    {
        return $this->userWith(['view directory', 'manage directory']);
    }

    protected function profile(array $attributes = []): DirectoryProfile
    {
        static $n = 0;
        $n++;

        return DirectoryProfile::query()->create($attributes + [
            'entry_id' => 'entry-'.$n,
            'site' => 'default',
            'collection' => 'choirs',
            'title' => 'Chor '.$n,
            'city' => 'Berlin',
            'visibility' => 'public',
            'hidden' => false,
            'published' => true,
            'lat' => 52.5,
            'lng' => 13.4,
        ]);
    }

    /** @return array<int, string> */
    protected function titles(string $query = ''): array
    {
        return array_column($this->getJson('/cp/directory'.$query)->assertOk()->json('data'), 'title');
    }

    protected function filters(array $filters): string
    {
        return '?filters='.urlencode(base64_encode(json_encode($filters)));
    }
}

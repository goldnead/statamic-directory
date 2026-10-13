<?php

namespace Goldnead\Directory\Claims;

use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Services\Collections;
use Goldnead\Teams\Contracts\ClaimResource;
use Goldnead\Teams\Exceptions\ClaimException;
use Goldnead\Teams\Models\Team;
use Illuminate\Support\Facades\DB;
use LogicException;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class DirectoryClaimResource implements ClaimResource
{
    public function lock(string $key, string $site): void
    {
        $this->assertInTransaction();
        $this->assertCurrentSite($site);

        $profile = DirectoryProfile::query()
            ->where('entry_id', $key)
            ->where('site', $site)
            ->lockForUpdate()
            ->first();

        if (! $profile || $profile->team_id !== null || $profile->hidden) {
            $this->unavailable();
        }

        $this->canonicalEntry($key, $site);
    }

    public function teamName(string $key, string $site): string
    {
        $this->assertCurrentSite($site);
        $entry = $this->canonicalEntry($key, $site);
        $fields = Collections::fields($entry->collectionHandle());
        $data = $entry->data()->all();
        $name = trim((string) ($data[$fields['title']] ?? $entry->get('title') ?? ''));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->unavailable();
        }

        return $name;
    }

    public function bind(string $key, string $site, Team $team): void
    {
        $this->assertInTransaction();
        $this->lock($key, $site);

        $teamId = $team->getKey();
        if (! is_numeric($teamId) || (int) $teamId < 1) {
            $this->unavailable();
        }

        $updated = DirectoryProfile::query()
            ->where('entry_id', $key)
            ->where('site', $site)
            ->whereNull('team_id')
            ->update(['team_id' => (int) $teamId, 'updated_at' => now()]);

        if ($updated !== 1) {
            $this->unavailable();
        }
    }

    private function canonicalEntry(string $key, string $site): EntryContract
    {
        $entry = Entry::find($key);
        $entry = $entry?->in($site);

        if (! $entry || (string) $entry->id() !== $key
            || (string) $entry->locale() !== $site
            || ! Collections::has($entry->collectionHandle())) {
            $this->unavailable();
        }

        $hiddenField = Collections::fields($entry->collectionHandle())['hidden'];
        if ((bool) ($entry->data()->all()[$hiddenField] ?? false)) {
            $this->unavailable();
        }

        return $entry;
    }

    private function assertCurrentSite(string $site): void
    {
        if ($site !== Site::current()->handle()) {
            $this->unavailable();
        }
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Directory claim operations must run inside a database transaction.');
        }
    }

    private function unavailable(): never
    {
        throw new ClaimException(ClaimException::RESOURCE_UNAVAILABLE);
    }
}

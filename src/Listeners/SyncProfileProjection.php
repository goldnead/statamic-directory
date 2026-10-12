<?php

namespace Goldnead\Directory\Listeners;

use Goldnead\Directory\Services\ProfileProjection;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;

class SyncProfileProjection
{
    public function __construct(private ProfileProjection $projection) {}

    public function handleEntrySaved(EntrySaved $event): void
    {
        $this->projection->sync($event->entry);
    }

    public function handleEntryDeleted(EntryDeleted $event): void
    {
        $this->projection->forget($event->entry);
    }
}

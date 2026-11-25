<?php

namespace Goldnead\Directory\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Progress of the last projection rebuild, kept in the cache so the CP can poll it. */
class RebuildStatus
{
    public const KEY = 'directory.rebuild-status';

    /** A queued or running status that has not been updated for this long belongs to a dead worker. */
    private const ORPHANED_AFTER_MINUTES = 15;

    public static function get(): ?array
    {
        $status = Cache::get(self::KEY);

        return is_array($status) ? $status : null;
    }

    public static function put(string $state, array $extra = []): void
    {
        Cache::put(self::KEY, ['state' => $state, 'updated_at' => now()->toIso8601String()] + $extra, now()->addDay());
    }

    public static function running(): bool
    {
        $status = self::get() ?? [];

        if (! in_array($status['state'] ?? null, ['queued', 'running'], true)) {
            return false;
        }

        $updatedAt = $status['updated_at'] ?? null;

        return is_string($updatedAt)
            && Carbon::parse($updatedAt)->isAfter(now()->subMinutes(self::ORPHANED_AFTER_MINUTES));
    }
}

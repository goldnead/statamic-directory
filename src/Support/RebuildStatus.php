<?php

namespace Goldnead\Directory\Support;

use Illuminate\Support\Facades\Cache;

/** Progress of the last projection rebuild, kept in the cache so the CP can poll it. */
class RebuildStatus
{
    public const KEY = 'directory.rebuild-status';

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
        return in_array(self::get()['state'] ?? null, ['queued', 'running'], true);
    }
}

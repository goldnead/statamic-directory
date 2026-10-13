<?php

namespace Goldnead\Directory\Services;

/**
 * Resolves the configured profile collections. `directory.collections` (handle => ['fields' => [],
 * 'filters' => []]) wins; otherwise the legacy `directory.collection` string is used with the
 * global `fields` and `filters`. Per collection, missing keys fall back to the global ones.
 */
class Collections
{
    /** @return array<string, array{fields: array, filters: array}> */
    public static function all(): array
    {
        $configured = config('directory.collections');
        $configured = is_array($configured) && $configured !== [] ? $configured : [(string) config('directory.collection') => []];
        $all = [];

        foreach ($configured as $handle => $settings) {
            $all[(string) $handle] = [
                'fields' => (array) ($settings['fields'] ?? []) + (array) config('directory.fields', []),
                'filters' => (array) ($settings['filters'] ?? config('directory.filters', [])),
            ];
        }

        return $all;
    }

    public static function handles(): array
    {
        return array_keys(self::all());
    }

    public static function default(): string
    {
        return self::handles()[0];
    }

    public static function has(?string $handle): bool
    {
        return $handle !== null && isset(self::all()[$handle]);
    }

    public static function fields(?string $handle): array
    {
        return self::all()[$handle ?? self::default()]['fields'] ?? [];
    }

    public static function filters(?string $handle): array
    {
        return self::all()[$handle ?? self::default()]['filters'] ?? [];
    }

    /** Every filter column across all collections (projection columns are shared). */
    public static function filterColumns(): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map(
            fn (array $c) => array_keys($c['filters']),
            self::all()
        )))));
    }
}

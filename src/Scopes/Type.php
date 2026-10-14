<?php

namespace Goldnead\Directory\Scopes;

use Goldnead\Directory\Services\Collections;
use Goldnead\Directory\Services\DirectoryStats;

class Type extends DirectoryFilter
{
    protected static $handle = 'directory_type';

    protected $pinned = true;

    public static function title()
    {
        return __('directory::cp.filter_type');
    }

    public function fieldItems()
    {
        return [
            'collection' => [
                'display' => __('directory::cp.filter_type'),
                'type' => 'select',
                'clearable' => true,
                'placeholder' => __('directory::cp.filter_any'),
                'options' => collect(Collections::handles())
                    ->mapWithKeys(fn (string $handle) => [$handle => DirectoryStats::title($handle)])
                    ->all(),
            ],
        ];
    }

    public function apply($query, $values)
    {
        if (! $value = $values['collection'] ?? null) {
            return;
        }

        $query->where('collection', $value);
    }

    public function badge($values)
    {
        return __('directory::cp.filter_type').': '.DirectoryStats::title((string) ($values['collection'] ?? ''));
    }
}

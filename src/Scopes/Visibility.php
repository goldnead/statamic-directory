<?php

namespace Goldnead\Directory\Scopes;

class Visibility extends DirectoryFilter
{
    protected static $handle = 'directory_visibility';

    protected $pinned = true;

    public static function title()
    {
        return __('directory::cp.filter_visibility');
    }

    public function fieldItems()
    {
        return [
            'visibility' => [
                'display' => __('directory::cp.filter_visibility'),
                'type' => 'select',
                'clearable' => true,
                'placeholder' => __('directory::cp.filter_any'),
                'options' => [
                    'public' => __('directory::cp.visibility_public'),
                    'private' => __('directory::cp.visibility_private'),
                ],
            ],
        ];
    }

    public function apply($query, $values)
    {
        match ($values['visibility'] ?? null) {
            'public' => $query->where('visibility', 'public'),
            'private' => $query->where('visibility', '!=', 'public'),
            default => null,
        };
    }

    public function badge($values)
    {
        $value = $values['visibility'] ?? '';

        return __('directory::cp.filter_visibility').': '.__('directory::cp.visibility_'.($value === 'public' ? 'public' : 'private'));
    }
}

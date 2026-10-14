<?php

namespace Goldnead\Directory\Scopes;

class Hidden extends YesNoFilter
{
    protected static $handle = 'directory_hidden';

    protected static function label(): string
    {
        return __('directory::cp.filter_hidden');
    }

    protected function constrain($query, bool $yes): void
    {
        $query->where('hidden', $yes);
    }
}

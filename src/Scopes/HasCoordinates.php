<?php

namespace Goldnead\Directory\Scopes;

class HasCoordinates extends YesNoFilter
{
    protected static $handle = 'directory_has_coordinates';

    protected static function label(): string
    {
        return __('directory::cp.filter_has_coordinates');
    }

    protected function constrain($query, bool $yes): void
    {
        if ($yes) {
            $query->whereNotNull('lat')->whereNotNull('lng');

            return;
        }

        $query->where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'));
    }
}

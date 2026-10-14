<?php

namespace Goldnead\Directory\Scopes;

use Statamic\Query\Scopes\Filter;

/**
 * Statamic registers scopes globally and `visibleTo()` defaults to true, so a filter that does
 * not answer that question would turn up on the Entries and Users listings as well.
 */
abstract class DirectoryFilter extends Filter
{
    public const LISTING_KEY = 'directory';

    public function visibleTo($key)
    {
        return $key === self::LISTING_KEY;
    }
}

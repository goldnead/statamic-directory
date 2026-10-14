<?php

namespace Goldnead\Directory\Scopes;

class HasTeam extends YesNoFilter
{
    protected static $handle = 'directory_has_team';

    protected static function label(): string
    {
        return __('directory::cp.filter_has_team');
    }

    protected function constrain($query, bool $yes): void
    {
        $yes ? $query->whereNotNull('team_id') : $query->whereNull('team_id');
    }
}

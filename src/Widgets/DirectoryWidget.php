<?php

namespace Goldnead\Directory\Widgets;

use Goldnead\Directory\Services\DirectoryStats;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class DirectoryWidget extends Widget
{
    protected static $handle = 'directory';

    public static function title()
    {
        return __('directory::cp.nav');
    }

    public function component()
    {
        if (! User::current()?->can('view directory')) {
            return;
        }

        return VueComponent::render('directory-widget', [
            'title' => $this->config('title', __('directory::cp.nav')),
            'url' => cp_route('directory.index'),
            'types' => DirectoryStats::perType(),
        ]);
    }
}

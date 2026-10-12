<?php

namespace Goldnead\Directory;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function register(): void
    {
        parent::register();

        $defaults = require __DIR__.'/../config/directory.php';
        $overrides = $this->app['config']->get('directory', []);

        $this->app['config']->set('directory', array_replace_recursive($defaults, $overrides));
    }

    public function bootAddon()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

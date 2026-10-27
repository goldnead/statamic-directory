<?php

namespace Goldnead\Directory;

use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    // Tags, widgets and the filters in src/Scopes are discovered by core.
    protected $commands = [Console\RebuildCommand::class];

    /** The three values must byte-match `laravel()` in vite.config.js. */
    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function register(): void
    {
        parent::register();

        $defaults = require __DIR__.'/../config/directory.php';
        $overrides = $this->app['config']->get('directory', []);

        $this->app['config']->set('directory', array_replace_recursive($defaults, $overrides));

        $this->app->singleton(Search\SearchEngine::class, function ($app): Search\SearchEngine {
            $config = (array) config('directory.search.meilisearch');

            return config('directory.search.driver') === 'meilisearch' && ! empty($config['host'])
                ? new Search\MeilisearchEngine($config)
                : new Search\NullEngine;
        });
    }

    public function bootAddon()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerNavigation()->registerPermissions();
    }

    protected function registerNavigation(): self
    {
        Nav::extend(function ($nav): void {
            $nav->create(__('directory::cp.nav'))
                ->section('Tools')
                ->icon('map-search')
                ->route('directory.index')
                ->can('view directory');
        });

        return $this;
    }

    protected function registerPermissions(): self
    {
        Permission::extend(function (): void {
            Permission::group('directory', __('directory::cp.nav'), function (): void {
                Permission::register('view directory')
                    ->label(__('directory::cp.permission_view'))
                    ->children([
                        Permission::make('manage directory')->label(__('directory::cp.permission_manage')),
                    ]);
            });
        });

        return $this;
    }
}

<?php

namespace Rvzug\LaravelSeoUrls\Providers;

use Illuminate\Support\ServiceProvider;
use Rvzug\LaravelSeoUrls\Console\Commands\RenameRouteNameCommand;

class SeoUrlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RenameRouteNameCommand::class,
            ]);
        }
    }
}

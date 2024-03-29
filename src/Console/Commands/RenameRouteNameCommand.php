<?php

namespace Rvzug\LaravelSeoUrls\Console\Commands;

use Illuminate\Console\Command;
use Rvzug\LaravelSeoUrls\Models\SeoUrl;

class RenameRouteNameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seourls:rename-route-name {old} {new}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renames the route name if there are already seo urls with the old route name.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SeoUrl::query()
            ->where('route_name', $this->argument('old'))
            ->update([
                'route_name' => $this->argument('new'),
            ]);

        $this->info('All relevant seo urls have been updated.');
    }
}

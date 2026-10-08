<?php

namespace App\Providers;

use App\Services\Crawler\FacebookCrawler;
use App\Services\Crawler\NodeFacebookCrawler;
use App\Support\CrawlNotifications;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FacebookCrawler::class, NodeFacebookCrawler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Crawl progress / completion notices on every page of the layout.
        View::composer('layouts.app', function ($view) {
            $runs = request()->hasSession()
                ? CrawlNotifications::pull(request()->session())
                : ['finished' => collect(), 'running' => collect()];
            $view->with(['crawlFinished' => $runs['finished'], 'crawlRunning' => $runs['running']]);
        });
    }
}

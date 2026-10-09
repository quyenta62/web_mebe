<?php

namespace App\Providers;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Services\Crawler\FacebookCrawler;
use App\Services\Crawler\NodeFacebookCrawler;
use App\Support\CrawlNotifications;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        // Crawl progress / completion notices: on every page of the layout, and in posts.index for
        // its "Tải dữ liệu mới" button (child views render before the layout).
        View::composer(['layouts.app', 'posts.index'], function ($view) {
            $runs = $this->crawlNotices();
            $view->with(['crawlFinished' => $runs['finished'], 'crawlRunning' => $runs['running']]);
        });

        // Header: when data was last refreshed (end of the latest successful crawl, any group).
        View::composer('layouts.app', function ($view) {
            $finishedAt = CrawlRun::where('status', CrawlRunStatus::Success)->max('finished_at');
            $view->with('latestCrawlAt', $finishedAt === null ? null : Carbon::parse($finishedAt, 'UTC'));
        });
    }

    /**
     * pull() forgets finished runs, so it must run once per request: computed on first use
     * and kept in the request attributes for the other views.
     *
     * @return array{finished: Collection<int, CrawlRun>, running: Collection<int, CrawlRun>}
     */
    private function crawlNotices(): array
    {
        $request = request();
        if (! $request->attributes->has('crawl_notices')) {
            $request->attributes->set('crawl_notices', $request->hasSession()
                ? CrawlNotifications::pull($request->session())
                : ['finished' => collect(), 'running' => collect()]);
        }

        return $request->attributes->get('crawl_notices');
    }
}

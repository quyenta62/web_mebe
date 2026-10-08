<?php

namespace App\Jobs\Middleware;

use App\Services\Crawler\CrawlLimits;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Lets at most `crawler.max_concurrent` crawl jobs run at once (one browser each),
 * whatever the number of queue workers. A job without a free slot goes back to
 * the queue and is retried later.
 */
class LimitCrawlerConcurrency
{
    public const RELEASE_SECONDS = 30;

    public function handle(object $job, Closure $next): void
    {
        $slots = max(1, (int) config('crawler.max_concurrent'));
        $ttl = (int) ($job->timeout ?? CrawlLimits::longestTimeoutSeconds()) + 300;

        for ($slot = 1; $slot <= $slots; $slot++) {
            $lock = Cache::lock("facebook-crawl:slot:{$slot}", $ttl);
            if ($lock->get()) {
                try {
                    $next($job);
                } finally {
                    $lock->release();
                }

                return;
            }
        }

        $job->release(self::RELEASE_SECONDS);
    }
}

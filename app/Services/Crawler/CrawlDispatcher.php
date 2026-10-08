<?php

namespace App\Services\Crawler;

use App\Enums\CrawlRunStatus;
use App\Jobs\CrawlFacebookGroupJob;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Support\Facades\Cache;

/**
 * Queues crawls: creates the "pending" crawl_run and dispatches the job.
 * A group that already has a pending or running crawl is not queued again.
 */
class CrawlDispatcher
{
    /** @return CrawlRun|null null when the group is already queued or being crawled. */
    public function queue(FacebookGroup $group, ?int $maxPosts = null): ?CrawlRun
    {
        $limits = CrawlLimits::forPosts($maxPosts);

        return Cache::lock("facebook-crawl:queue:{$group->id}", 10)->block(5, function () use ($group, $limits) {
            $this->failStaleRuns($group);

            $active = $group->crawlRuns()
                ->whereIn('status', [CrawlRunStatus::Pending, CrawlRunStatus::Running])
                ->exists();
            if ($active) {
                return null;
            }

            $run = $group->crawlRuns()->create(['status' => CrawlRunStatus::Pending, 'max_posts' => $limits->maxPosts]);
            CrawlFacebookGroupJob::dispatch($run->id, $limits->maxPosts);

            return $run;
        });
    }

    /** Runs stuck in pending/running (e.g. the worker died) must not block new crawls forever. */
    private function failStaleRuns(FacebookGroup $group): void
    {
        $group->crawlRuns()
            ->whereIn('status', [CrawlRunStatus::Pending, CrawlRunStatus::Running])
            ->where('created_at', '<', now()->subMinutes((int) config('crawler.stale_after_minutes')))
            ->update([
                'status' => CrawlRunStatus::Failed,
                'error_message' => 'STALE: Lần crawl không hoàn tất (queue worker không chạy hoặc bị dừng giữa chừng).',
                'finished_at' => now(),
            ]);
    }
}

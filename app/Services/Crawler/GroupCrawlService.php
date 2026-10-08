<?php

namespace App\Services\Crawler;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Crawls one group and records the attempt in crawl_runs:
 * pending -> running -> success | failed.
 */
class GroupCrawlService
{
    public function __construct(
        private readonly FacebookCrawler $crawler,
        private readonly PostImporter $importer,
    ) {}

    public function crawl(FacebookGroup $group, ?CrawlRun $run = null): CrawlRun
    {
        $run ??= $group->crawlRuns()->create();
        $limits = CrawlLimits::forPosts($run->max_posts);

        // One browser session per group at a time.
        $lock = Cache::lock("facebook-crawl:group:{$group->id}", $limits->timeoutSeconds + 120);
        if (! $lock->get()) {
            return $this->fail($run, $group, 'ALREADY_RUNNING: Group này đang được crawl ở một tiến trình khác.');
        }

        try {
            $run->update(['status' => CrawlRunStatus::Running, 'started_at' => now()]);
            Log::info('Facebook crawl started', ['group' => $group->facebook_group_id, 'crawl_run' => $run->id]);

            $result = $this->crawler->crawl($group->facebook_group_id, $limits);
            // Partial results of a failed crawl are real posts too, so they are kept.
            $import = $this->importer->import($group, $result->posts);
            $counts = ['posts_found' => $import->found, 'posts_created' => $import->created];

            if (! $result->successful) {
                return $this->fail($run, $group, (string) $result->error(), $counts);
            }

            $run->update($counts + ['status' => CrawlRunStatus::Success, 'finished_at' => now()]);
            $group->forceFill(['last_crawled_at' => now()])->save();
            Log::info('Facebook crawl finished', [
                'group' => $group->facebook_group_id,
                'crawl_run' => $run->id,
                'found' => $import->found,
                'created' => $import->created,
                'updated' => $import->updated,
                'skipped' => $import->skipped,
            ]);

            return $run;
        } catch (Throwable $e) {
            $this->fail($run, $group, 'UNEXPECTED_ERROR: '.$e->getMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function fail(CrawlRun $run, FacebookGroup $group, string $error, array $counts = []): CrawlRun
    {
        $run->update($counts + [
            'status' => CrawlRunStatus::Failed,
            'error_message' => mb_substr($error, 0, 5000),
            'started_at' => $run->started_at ?? now(),
            'finished_at' => now(),
        ]);
        Log::error('Facebook crawl failed', ['group' => $group->facebook_group_id, 'crawl_run' => $run->id, 'error' => $error]);

        return $run;
    }
}

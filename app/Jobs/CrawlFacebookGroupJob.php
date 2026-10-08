<?php

namespace App\Jobs;

use App\Enums\CrawlRunStatus;
use App\Exceptions\CrawlAttemptFailed;
use App\Jobs\Middleware\LimitCrawlerConcurrency;
use App\Models\CrawlRun;
use App\Services\Crawler\CrawlLimits;
use App\Services\Crawler\GroupCrawlService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Crawls one group for an existing crawl_run (created as "pending" when queued).
 *
 * Retries: only temporary failures (timeout, crawler crash, unexpected exception) are
 * retried, at most 3 times. Login/checkpoint/access errors are final: retrying them
 * would only hit Facebook again while the account is blocked.
 */
class CrawlFacebookGroupJob implements ShouldQueue
{
    use Queueable;

    /** Error codes worth another attempt. */
    public const TRANSIENT_ERRORS = ['TIMEOUT', 'CRAWLER_ERROR', 'UNEXPECTED_ERROR'];

    /** Real failures allowed; waiting for a concurrency slot does not count. */
    public int $maxExceptions = 3;

    public bool $failOnTimeout = true;

    public int $timeout;

    public function __construct(public readonly int $crawlRunId, ?int $maxPosts = null)
    {
        // Longer than the crawler process timeout (crawl timeout + 60 s), so the
        // crawler is stopped cleanly before the worker kills the job.
        $this->timeout = CrawlLimits::forPosts($maxPosts)->timeoutSeconds + 120;
        $this->onQueue(config('crawler.queue'));
    }

    /** Jobs waiting for a free slot are released many times; give up after 2 hours. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /** @return list<int> Seconds to wait before retry 1, 2, ... */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new LimitCrawlerConcurrency];
    }

    public function handle(GroupCrawlService $service): void
    {
        $run = CrawlRun::with('group')->find($this->crawlRunId);
        if ($run === null || $run->status === CrawlRunStatus::Success) {
            return;
        }

        if ($run->group === null || $run->group->trashed()) {
            $run->update([
                'status' => CrawlRunStatus::Failed,
                'error_message' => 'GROUP_DELETED: Group đã bị xoá trước khi crawl.',
                'finished_at' => now(),
            ]);

            return;
        }

        $run = $service->crawl($run->group, $run);

        if ($run->status === CrawlRunStatus::Failed && in_array($this->errorCode($run), self::TRANSIENT_ERRORS, true)) {
            throw new CrawlAttemptFailed((string) $run->error_message);
        }
    }

    /** Called once all attempts are used up (or on job timeout). */
    public function failed(?Throwable $exception): void
    {
        $run = CrawlRun::find($this->crawlRunId);
        if ($run === null) {
            return;
        }

        $message = $exception?->getMessage() ?: 'Job failed';
        if ($run->status !== CrawlRunStatus::Failed) {
            $run->update([
                'status' => CrawlRunStatus::Failed,
                'error_message' => mb_substr("JOB_FAILED: {$message}", 0, 5000),
                'finished_at' => now(),
            ]);
        }

        Log::error('Facebook crawl job failed', ['crawl_run' => $run->id, 'attempts' => $this->attempts(), 'error' => $message]);
    }

    private function errorCode(CrawlRun $run): string
    {
        return strtok((string) $run->error_message, ':') ?: '';
    }
}

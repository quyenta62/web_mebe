<?php

namespace Tests\Feature\Queue;

use App\Enums\CrawlRunStatus;
use App\Exceptions\CrawlAttemptFailed;
use App\Jobs\CrawlFacebookGroupJob;
use App\Jobs\Middleware\LimitCrawlerConcurrency;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use App\Services\Crawler\CrawlerResult;
use App\Services\Crawler\CrawlLimits;
use App\Services\Crawler\FacebookCrawler;
use App\Services\Crawler\GroupCrawlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Fakes\FakeFacebookCrawler;
use Tests\TestCase;

class CrawlQueueTest extends TestCase
{
    use RefreshDatabase;

    private FakeFacebookCrawler $crawler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new FakeFacebookCrawler;
        $this->app->instance(FacebookCrawler::class, $this->crawler);
    }

    private function runJob(CrawlRun $run): CrawlFacebookGroupJob
    {
        $job = (new CrawlFacebookGroupJob($run->id))->withFakeQueueInteractions();
        $job->handle($this->app->make(GroupCrawlService::class));

        return $job;
    }

    public function test_crawl_all_queues_only_active_groups(): void
    {
        Queue::fake();
        $active = FacebookGroup::factory()->count(2)->create();
        FacebookGroup::factory()->inactive()->create();
        FacebookGroup::factory()->create()->delete();

        $this->artisan('facebook:crawl-all')->expectsOutputToContain('Đã đưa 2/2 group')->assertSuccessful();

        Queue::assertPushedOn('crawler', CrawlFacebookGroupJob::class);
        Queue::assertPushed(CrawlFacebookGroupJob::class, 2);
        $this->assertSame($active->pluck('id')->sort()->values()->all(), CrawlRun::pluck('facebook_group_id')->sort()->values()->all());
        $this->assertSame([CrawlRunStatus::Pending], CrawlRun::all()->pluck('status')->unique()->values()->all());
        $this->assertSame([], $this->crawler->calls, 'nothing is crawled synchronously');
    }

    public function test_a_group_already_queued_or_running_is_skipped(): void
    {
        Queue::fake();
        $group = FacebookGroup::factory()->create();
        CrawlRun::factory()->for($group, 'group')->create(['status' => CrawlRunStatus::Running]);

        $this->artisan('facebook:crawl-all')->expectsOutputToContain('skipped')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_stale_runs_are_failed_and_do_not_block_new_crawls(): void
    {
        Queue::fake();
        $group = FacebookGroup::factory()->create();
        $stale = CrawlRun::factory()->for($group, 'group')->create(['status' => CrawlRunStatus::Running]);
        $stale->forceFill(['created_at' => now()->subHours(4)])->save();

        $this->artisan('facebook:crawl-all')->assertSuccessful();

        $this->assertSame(CrawlRunStatus::Failed, $stale->fresh()->status);
        $this->assertStringStartsWith('STALE', $stale->fresh()->error_message);
        Queue::assertPushed(CrawlFacebookGroupJob::class, 1);
    }

    public function test_crawl_button_queues_a_job(): void
    {
        Queue::fake();
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);

        $this->post("/groups/{$group->id}/crawl", ['max_posts' => 100])
            ->assertRedirect('/groups')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'crawl 100 bài mới nhất'));
        $run = CrawlRun::sole();
        $this->assertSame(100, $run->max_posts);
        Queue::assertPushed(CrawlFacebookGroupJob::class, fn ($job) => $job->crawlRunId === $run->id
            && $job->timeout === CrawlLimits::forPosts(100)->timeoutSeconds + 120);

        // Second click while pending: nothing new queued, button disabled.
        $this->post("/groups/{$group->id}/crawl", ['max_posts' => 20])->assertSessionHas('status', fn ($m) => str_contains($m, 'đang chờ'));
        Queue::assertPushed(CrawlFacebookGroupJob::class, 1);
        $this->get('/groups')->assertSee('Đang chờ')->assertSee('disabled', false);
    }

    public function test_job_crawls_and_marks_the_run_successful(): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);
        $run = CrawlRun::factory()->for($group, 'group')->create();
        $this->crawler->returns(CrawlerResult::succeeded([FakeFacebookCrawler::row('123456789', '1001')]));

        $this->runJob($run)->assertNotFailed();

        $this->assertSame(CrawlRunStatus::Success, $run->fresh()->status);
        $this->assertSame(1, FacebookPost::count());
        $this->assertNotNull($group->fresh()->last_crawled_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function transientErrors(): array
    {
        return ['timeout' => ['TIMEOUT'], 'crawler crash' => ['CRAWLER_ERROR']];
    }

    #[DataProvider('transientErrors')]
    public function test_temporary_failures_are_retried(string $code): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);
        $run = CrawlRun::factory()->for($group, 'group')->create();
        $this->crawler->returns(CrawlerResult::failed($code, 'boom'));

        $this->expectException(CrawlAttemptFailed::class);
        $this->runJob($run);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function finalErrors(): array
    {
        return ['login' => ['LOGIN_REQUIRED'], 'checkpoint' => ['CHECKPOINT'], 'access' => ['GROUP_UNAVAILABLE']];
    }

    #[DataProvider('finalErrors')]
    public function test_blocking_failures_are_not_retried(string $code): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);
        $run = CrawlRun::factory()->for($group, 'group')->create();
        $this->crawler->returns(CrawlerResult::failed($code, 'blocked'));

        $this->runJob($run)->assertNotFailed()->assertNotReleased();

        $this->assertSame(CrawlRunStatus::Failed, $run->fresh()->status);
        $this->assertSame("{$code}: blocked", $run->fresh()->error_message);
    }

    public function test_unexpected_exceptions_bubble_up_for_retry(): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);
        $run = CrawlRun::factory()->for($group, 'group')->create();
        $this->crawler->throws();

        try {
            $this->runJob($run);
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame('Disk full', $e->getMessage());
        }
        $this->assertSame('UNEXPECTED_ERROR: Disk full', $run->fresh()->error_message);
    }

    public function test_failed_hook_marks_unfinished_runs_failed(): void
    {
        $run = CrawlRun::factory()->create(['status' => CrawlRunStatus::Running]);

        (new CrawlFacebookGroupJob($run->id))->failed(new RuntimeException('Job timed out'));

        $this->assertSame(CrawlRunStatus::Failed, $run->fresh()->status);
        $this->assertSame('JOB_FAILED: Job timed out', $run->fresh()->error_message);
    }

    public function test_failed_hook_keeps_the_crawler_error_message(): void
    {
        $run = CrawlRun::factory()->failed('TIMEOUT: crawler quá 300 giây')->create();

        (new CrawlFacebookGroupJob($run->id))->failed(new CrawlAttemptFailed('TIMEOUT: crawler quá 300 giây'));

        $this->assertSame('TIMEOUT: crawler quá 300 giây', $run->fresh()->error_message);
    }

    public function test_job_for_a_deleted_group_fails_without_crawling(): void
    {
        $group = FacebookGroup::factory()->create();
        $run = CrawlRun::factory()->for($group, 'group')->create();
        $group->delete();

        $this->runJob($run);

        $this->assertSame([], $this->crawler->calls);
        $this->assertStringStartsWith('GROUP_DELETED', $run->fresh()->error_message);
    }

    public function test_job_settings(): void
    {
        config(['crawler.timeout_seconds' => 300, 'crawler.queue' => 'crawler']);
        $job = new CrawlFacebookGroupJob(1);

        $this->assertSame('crawler', $job->queue);
        $this->assertSame(420, $job->timeout);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame([60, 300], $job->backoff());
        $this->assertGreaterThan(now()->addHour(), $job->retryUntil());
        $this->assertInstanceOf(LimitCrawlerConcurrency::class, $job->middleware()[0]);
        // retry_after must exceed the longest job (200 posts), or a running crawl is started twice.
        $this->assertGreaterThan((new CrawlFacebookGroupJob(1, 200))->timeout, (int) config('queue.connections.database.retry_after'));
    }

    public function test_concurrency_limit_releases_jobs_without_a_free_slot(): void
    {
        config(['crawler.max_concurrent' => 1]);
        $busy = Cache::lock('facebook-crawl:slot:1', 60);
        $busy->get();

        $job = (new CrawlFacebookGroupJob(1))->withFakeQueueInteractions();
        $ran = false;
        (new LimitCrawlerConcurrency)->handle($job, function () use (&$ran) {
            $ran = true;
        });

        $this->assertFalse($ran);
        $job->assertReleased(LimitCrawlerConcurrency::RELEASE_SECONDS);

        // With two slots, the second one is free.
        config(['crawler.max_concurrent' => 2]);
        (new LimitCrawlerConcurrency)->handle($job, function () use (&$ran) {
            $ran = true;
        });
        $this->assertTrue($ran);
        $this->assertTrue(Cache::lock('facebook-crawl:slot:2', 1)->get(), 'slot is released after the job');
        $busy->release();
    }
}

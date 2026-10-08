<?php

namespace Tests\Feature\Crawler;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use App\Services\Crawler\CrawlerResult;
use App\Services\Crawler\FacebookCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeFacebookCrawler;
use Tests\TestCase;

class CrawlCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeFacebookCrawler $crawler;

    private FacebookGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new FakeFacebookCrawler;
        $this->app->instance(FacebookCrawler::class, $this->crawler);
        $this->group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);
    }

    private function rows(array $postIds, array $overrides = []): array
    {
        return array_map(fn ($id) => FakeFacebookCrawler::row('123456789', (string) $id, $overrides), $postIds);
    }

    public function test_successful_crawl_saves_posts_and_records_the_run(): void
    {
        $this->travelTo('2026-10-07 04:00:00');
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001, 1002, 1003])));

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        $this->assertSame(['123456789'], $this->crawler->calls);
        $this->assertSame(3, $this->group->posts()->count());
        $this->assertDatabaseHas('facebook_posts', [
            'facebook_group_id' => $this->group->id,
            'facebook_post_id' => '1001',
            'author_name' => 'Nguyễn Văn A',
            'content' => 'Pass lại xe đẩy cho bé',
            'post_url' => 'https://www.facebook.com/groups/123456789/posts/1001/',
            'posted_at' => '2026-10-07 03:30:00', // stored in UTC
        ]);

        $this->assertSame(
            ['https://scontent.fhan2-1.fna.fbcdn.net/v/t39/1001.jpg?oh=x&oe=1'],
            FacebookPost::where('facebook_post_id', '1001')->sole()->image_urls,
        );

        $run = CrawlRun::sole();
        $this->assertSame(CrawlRunStatus::Success, $run->status);
        $this->assertSame(3, $run->posts_found);
        $this->assertSame(3, $run->posts_created);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->error_message);
        $this->assertSame('2026-10-07 04:00:00', $this->group->fresh()->last_crawled_at->toDateTimeString());
    }

    public function test_crawling_again_does_not_create_duplicates(): void
    {
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001, 1002])));
        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        // Second crawl: one known post (seen twice in the same batch) and one new post.
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1002, 1002, 1003])));
        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        $this->assertSame(['1001', '1002', '1003'], FacebookPost::orderBy('facebook_post_id')->pluck('facebook_post_id')->all());
        $second = CrawlRun::latest('id')->first();
        $this->assertSame(3, $second->posts_found);
        $this->assertSame(1, $second->posts_created);
    }

    public function test_known_posts_are_updated_but_never_blanked(): void
    {
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001])));
        $this->artisan('facebook:crawl', ['groupId' => '123456789']);

        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001], [
            'content' => 'Pass lại xe đẩy cho bé (đã sửa)',
            'posted_at' => null,
            'author_name' => null,
            'image_urls' => [],
        ])));
        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        $post = FacebookPost::sole();
        $this->assertSame('Pass lại xe đẩy cho bé (đã sửa)', $post->content);
        $this->assertSame(['https://scontent.fhan2-1.fna.fbcdn.net/v/t39/1001.jpg?oh=x&oe=1'], $post->image_urls);
        $this->assertSame('Nguyễn Văn A', $post->author_name);
        $this->assertSame('2026-10-07 03:30:00', $post->posted_at->toDateTimeString());
    }

    public function test_failed_crawl_records_the_error_and_keeps_partial_posts(): void
    {
        $this->crawler->returns(CrawlerResult::failed(
            'LOGIN_REQUIRED',
            'Facebook showed a login dialog after 1 post(s); more posts need a logged-in session',
            $this->rows([1001]),
        ));

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])
            ->expectsOutputToContain('LOGIN_REQUIRED')
            ->assertFailed();

        $run = CrawlRun::sole();
        $this->assertSame(CrawlRunStatus::Failed, $run->status);
        $this->assertStringStartsWith('LOGIN_REQUIRED: Facebook showed a login dialog', $run->error_message);
        $this->assertSame(1, $run->posts_found);
        $this->assertSame(1, $run->posts_created);
        $this->assertSame(1, FacebookPost::count());
        $this->assertNull($this->group->fresh()->last_crawled_at);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blockingErrors(): array
    {
        return [
            'checkpoint' => ['CHECKPOINT', 'Facebook redirected to a security checkpoint'],
            'session expired' => ['LOGIN_REQUIRED', 'Facebook session is expired or invalid'],
            'access denied' => ['GROUP_UNAVAILABLE', 'This group is private and the current account is not a member'],
        ];
    }

    #[DataProvider('blockingErrors')]
    public function test_blocking_errors_fail_the_run_clearly(string $code, string $message): void
    {
        $this->crawler->returns(CrawlerResult::failed($code, $message));

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertFailed();

        $run = CrawlRun::sole();
        $this->assertSame(CrawlRunStatus::Failed, $run->status);
        $this->assertSame("{$code}: {$message}", $run->error_message);
        $this->assertSame(0, $run->posts_found);
    }

    public function test_unexpected_exception_marks_the_run_failed(): void
    {
        $this->crawler->throws();

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertFailed();

        $run = CrawlRun::sole();
        $this->assertSame(CrawlRunStatus::Failed, $run->status);
        $this->assertSame('UNEXPECTED_ERROR: Disk full', $run->error_message);
    }

    public function test_a_group_already_being_crawled_is_not_crawled_twice(): void
    {
        $lock = Cache::lock("facebook-crawl:group:{$this->group->id}", 60);
        $lock->get();

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertFailed();

        $this->assertSame([], $this->crawler->calls);
        $this->assertStringStartsWith('ALREADY_RUNNING', CrawlRun::sole()->error_message);
        $lock->release();
    }

    public function test_unknown_deleted_or_invalid_groups_are_rejected_without_crawling(): void
    {
        $this->artisan('facebook:crawl', ['groupId' => '999999999'])
            ->expectsOutputToContain('chưa có trong danh sách')
            ->assertFailed();

        $this->group->delete();
        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertFailed();

        $this->artisan('facebook:crawl', ['groupId' => '123; rm -rf /'])->assertExitCode(2);

        $this->assertSame([], $this->crawler->calls);
        $this->assertSame(0, CrawlRun::count());
    }

    public function test_posts_option_sets_the_crawl_limits(): void
    {
        $this->artisan('facebook:crawl', ['groupId' => '123456789', '--posts' => '120'])
            ->expectsOutputToContain('Đang crawl 120 bài mới nhất')
            ->assertSuccessful();

        $this->assertSame(120, $this->crawler->limits[0]->maxPosts);
        $this->assertSame(120, CrawlRun::sole()->max_posts);

        $this->artisan('facebook:crawl', ['groupId' => '123456789', '--posts' => '0'])->assertExitCode(2);
        $this->artisan('facebook:crawl', ['groupId' => '123456789', '--posts' => '201'])->assertExitCode(2);
        $this->artisan('facebook:crawl', ['groupId' => '123456789', '--posts' => 'abc'])->assertExitCode(2);
        $this->assertCount(1, $this->crawler->calls);
    }

    public function test_inactive_groups_can_still_be_crawled_manually(): void
    {
        $this->group->update(['is_active' => false]);

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])
            ->expectsOutputToContain('Group đang tắt')
            ->assertSuccessful();
    }

    public function test_invalid_rows_are_skipped_or_sanitised(): void
    {
        $this->crawler->returns(CrawlerResult::succeeded([
            FakeFacebookCrawler::row('123456789', '1001', [
                'post_url' => 'javascript:alert(1)',
                'posted_at' => 'hôm qua',
                'author_name' => '   ',
            ]),
            FakeFacebookCrawler::row('555555555', '2002'), // another group (shared post)
            FakeFacebookCrawler::row('123456789', 'abc'),
            'not an object',
            ['post_id' => '1003'], // bare ID without prefix is accepted
        ]));

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        $this->assertSame(['1001', '1003'], FacebookPost::orderBy('facebook_post_id')->pluck('facebook_post_id')->all());
        $post = FacebookPost::where('facebook_post_id', '1001')->sole();
        $this->assertNull($post->post_url);
        $this->assertNull($post->posted_at);
        $this->assertNull($post->author_name);
        $this->assertSame(5, CrawlRun::sole()->posts_found);
        $this->assertSame(2, CrawlRun::sole()->posts_created);
    }

    public function test_image_urls_are_refreshed_on_recrawl_and_filtered(): void
    {
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001])));
        $this->artisan('facebook:crawl', ['groupId' => '123456789']);

        $fresh = 'https://scontent.fhan2-1.fna.fbcdn.net/v/t39/1001.jpg?oh=new&oe=2';
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001], ['image_urls' => [
            $fresh,
            $fresh, // duplicate
            'http://scontent.x.fbcdn.net/insecure.jpg',
            'https://evil.example/tracker.gif',
            'https://fbcdn.net.evil.example/x.jpg',
            'javascript:alert(1)',
            ['nested'],
        ]])));
        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        $this->assertSame([$fresh], FacebookPost::sole()->image_urls);
    }

    public function test_at_most_ten_images_are_kept(): void
    {
        $urls = array_map(fn ($i) => "https://scontent.x.fbcdn.net/{$i}.jpg", range(1, 12));
        $this->crawler->returns(CrawlerResult::succeeded($this->rows([1001], ['image_urls' => $urls])));

        $this->artisan('facebook:crawl', ['groupId' => '123456789'])->assertSuccessful();

        $this->assertSame(array_slice($urls, 0, 10), FacebookPost::sole()->image_urls);
    }
}

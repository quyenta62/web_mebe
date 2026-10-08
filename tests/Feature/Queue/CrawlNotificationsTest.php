<?php

namespace Tests\Feature\Queue;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrawlNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private FacebookGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->group = FacebookGroup::factory()->create(['name' => 'Hội mẹ bỉm', 'facebook_group_id' => '123456789']);
    }

    private function startCrawl(int $posts = 100): CrawlRun
    {
        $this->post("/groups/{$this->group->id}/crawl", ['max_posts' => $posts])->assertRedirect('/groups');

        return CrawlRun::latest('id')->first();
    }

    public function test_crawl_menu_offers_post_counts(): void
    {
        $this->get('/groups')
            ->assertOk()
            ->assertSee('Crawl ▾')
            ->assertSee('Số bài mới nhất cần lấy')
            ->assertSee('name="max_posts" value="200"', false)
            ->assertSee('type="number" name="max_posts" min="1" max="200"', false);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidCounts(): array
    {
        return ['missing' => [null], 'zero' => [0], 'too many' => [201], 'text' => ['abc'], 'negative' => [-5]];
    }

    #[DataProvider('invalidCounts')]
    public function test_invalid_post_counts_are_rejected(mixed $count): void
    {
        $this->from('/groups')
            ->post("/groups/{$this->group->id}/crawl", $count === null ? [] : ['max_posts' => $count])
            ->assertRedirect('/groups')
            ->assertSessionHasErrors('max_posts');

        Queue::assertNothingPushed();
        $this->get('/groups')->assertSee('Số bài cần crawl phải từ 1 đến 200.');
    }

    public function test_groups_page_shows_progress_and_refreshes_while_crawling(): void
    {
        $run = $this->startCrawl();

        $this->get('/groups')->assertSee('Đang chờ')->assertSee('http-equiv="refresh"', false);

        $run->update(['status' => CrawlRunStatus::Running, 'started_at' => now()]);
        $this->get('/groups')->assertSee('Đang crawl')->assertSee('100 bài mới nhất');

        // Other pages show the notice but do not auto-refresh.
        $this->get('/posts')->assertSee('Đang crawl')->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_success_is_announced_once(): void
    {
        $run = $this->startCrawl(100);
        $run->update(['status' => CrawlRunStatus::Success, 'posts_found' => 98, 'posts_created' => 23, 'finished_at' => now()]);

        $this->get('/posts')
            ->assertSee('Crawl xong')
            ->assertSee('lấy được 98/100 bài')
            ->assertSee('23 bài mới')
            ->assertSee('Ít hơn số yêu cầu')
            ->assertSee(route('posts.index', ['group' => $this->group->id]), false)
            ->assertDontSee('http-equiv="refresh"', false);

        $this->get('/groups')->assertDontSee('Crawl xong');
    }

    public function test_failure_is_announced_with_the_reason(): void
    {
        $run = $this->startCrawl();
        $run->update([
            'status' => CrawlRunStatus::Failed,
            'error_message' => 'LOGIN_REQUIRED: Facebook session is expired',
            'finished_at' => now(),
        ]);

        $this->get('/groups')
            ->assertSee('Crawl thất bại')
            ->assertSee('LOGIN_REQUIRED: Facebook session is expired')
            ->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_runs_started_elsewhere_are_not_announced(): void
    {
        CrawlRun::factory()->for($this->group, 'group')->succeeded()->create();

        $this->get('/groups')->assertDontSee('Crawl xong');
    }
}

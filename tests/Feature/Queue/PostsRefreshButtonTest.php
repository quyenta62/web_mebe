<?php

namespace Tests\Feature\Queue;

use App\Enums\CrawlRunStatus;
use App\Jobs\CrawlFacebookGroupJob;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** "Tải dữ liệu mới" on /posts: crawl the 20 most recent posts of every active group. */
class PostsRefreshButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_button_sits_next_to_the_title_and_posts_20(): void
    {
        FacebookGroup::factory()->count(2)->create();

        $this->get('/posts?keyword=pass')
            ->assertOk()
            ->assertSeeInOrder(['Posts</h1>', 'Tải dữ liệu mới'], false)
            ->assertSee('action="'.route('groups.crawl-all').'"', false)
            ->assertSee('name="max_posts" value="20"', false)
            ->assertSee('name="return_to" value="/posts?keyword=pass"', false);
    }

    public function test_click_queues_every_active_group_with_20_posts_and_returns_to_the_same_posts_view(): void
    {
        $active = FacebookGroup::factory()->count(2)->create();
        FacebookGroup::factory()->inactive()->create();
        FacebookGroup::factory()->create()->delete();

        $this->post('/groups/crawl-all', ['max_posts' => 20, 'return_to' => '/posts?keyword=pass&group='])
            ->assertRedirect('/posts?keyword=pass&group=')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'crawl 20 bài mới nhất cho 2/2 group active'));

        Queue::assertPushed(CrawlFacebookGroupJob::class, 2);
        $this->assertEqualsCanonicalizing($active->pluck('id')->all(), CrawlRun::pluck('facebook_group_id')->all());
        $this->assertSame([20], CrawlRun::pluck('max_posts')->unique()->values()->all());

        // While crawling: button disabled, page refreshes itself.
        $this->get('/posts')->assertSee('Đang tải dữ liệu…')->assertSee('http-equiv="refresh"', false);

        // Done: notices shown once, button back.
        CrawlRun::query()->update(['status' => CrawlRunStatus::Success, 'posts_found' => 20, 'posts_created' => 4, 'finished_at' => now()]);
        $this->get('/posts')->assertSee('Crawl xong')->assertSee('Tải dữ liệu mới')->assertDontSee('http-equiv="refresh"', false);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsafeReturnTargets(): array
    {
        return [
            'other site' => ['https://evil.example/posts'],
            'protocol-relative' => ['//evil.example/posts'],
            'other page' => ['/crawl-runs'],
            'not a string' => [['/posts']],
        ];
    }

    #[DataProvider('unsafeReturnTargets')]
    public function test_return_target_is_limited_to_the_posts_page(mixed $returnTo): void
    {
        FacebookGroup::factory()->create();

        $this->post('/groups/crawl-all', ['max_posts' => 20, 'return_to' => $returnTo])
            ->assertRedirect(route('groups.index'));
    }

    public function test_button_is_disabled_without_active_groups(): void
    {
        FacebookGroup::factory()->inactive()->create();

        $this->get('/posts')->assertSee('title="Không có group active"', false)
            ->assertDontSee('action="'.route('groups.crawl-all').'"', false);
    }
}

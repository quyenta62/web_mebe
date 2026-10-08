<?php

namespace Tests\Feature;

use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class PostsPageTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private FacebookGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = FacebookGroup::factory()->create(['name' => 'Hội mẹ bỉm']);
    }

    private function createPost(string $content, array $attributes = []): FacebookPost
    {
        return FacebookPost::factory()->for($this->group, 'group')->create(['content' => $content] + $attributes);
    }

    /** Contents of the posts listed for the given query, in page order. */
    private function search(array $query): array
    {
        $response = $this->actingAsAdmin()->get(route('posts.index', $query))->assertOk();

        return $response->viewData('posts')->pluck('content')->all();
    }

    public function test_guests_cannot_see_posts(): void
    {
        $this->configureAdmin();
        $this->get('/posts')->assertRedirect('/login');
    }

    public function test_listing_shows_group_author_content_time_and_facebook_link(): void
    {
        $this->createPost('Pass lại xe đẩy cho bé', [
            'author_name' => 'Nguyễn Văn A',
            'posted_at' => '2026-10-07 03:30:00', // UTC = 10:30 in Vietnam
            'post_url' => 'https://www.facebook.com/groups/1/posts/2/',
        ]);

        $this->actingAsAdmin()->get('/posts')
            ->assertOk()
            ->assertSee('Hội mẹ bỉm')
            ->assertSee('Nguyễn Văn A')
            ->assertSee('Pass lại xe đẩy cho bé')
            ->assertSee('2026-10-07 10:30')
            ->assertSee('href="https://www.facebook.com/groups/1/posts/2/"', false)
            ->assertSee('Open Facebook')
            ->assertSee('Hiển thị 1–1 / 1 posts');
    }

    public function test_empty_state(): void
    {
        $this->actingAsAdmin()->get('/posts')->assertOk()->assertSee('Không có bài viết nào phù hợp.')->assertSee('0 posts');
    }

    public function test_newest_posts_come_first(): void
    {
        $this->createPost('cũ', ['posted_at' => '2026-10-01 00:00:00']);
        $this->createPost('không rõ ngày', ['posted_at' => null]);
        $this->createPost('mới', ['posted_at' => '2026-10-07 00:00:00']);

        $this->assertSame(['mới', 'cũ', 'không rõ ngày'], $this->search([]));
    }

    public function test_pagination_keeps_filters(): void
    {
        FacebookPost::factory()->count(51)->for($this->group, 'group')->create(['content' => 'pass đồ']);
        FacebookPost::factory()->count(3)->for($this->group, 'group')->create(['content' => 'khác']);

        $page1 = $this->actingAsAdmin()->get('/posts?keyword=pass')->assertOk();
        $page1->assertSee('Hiển thị 1–50 / 51 posts');
        $this->assertCount(50, $page1->viewData('posts'));
        $page1->assertSee('keyword=pass&amp;page=2', false);

        $this->get('/posts?keyword=pass&page=2')->assertOk()->assertSee('Hiển thị 51–51 / 51 posts');
    }

    public function test_group_filter(): void
    {
        $other = FacebookGroup::factory()->create();
        $this->createPost('trong group');
        FacebookPost::factory()->for($other, 'group')->create(['content' => 'group khác']);

        $this->assertSame(['trong group'], $this->search(['group' => $this->group->id]));
        $this->assertSame(['group khác'], $this->search(['group' => $other->id]));
        $this->assertCount(2, $this->search(['group' => '']));
    }

    public function test_date_filter_uses_vietnam_calendar_days(): void
    {
        // Vietnam is UTC+7.
        $this->createPost('trước ngày', ['posted_at' => '2026-09-30 16:59:59']); // 2026-09-30 23:59:59 VN
        $this->createPost('đầu ngày', ['posted_at' => '2026-09-30 17:00:00']);   // 2026-10-01 00:00:00 VN
        $this->createPost('cuối ngày', ['posted_at' => '2026-10-07 16:59:59']);  // 2026-10-07 23:59:59 VN
        $this->createPost('sau ngày', ['posted_at' => '2026-10-07 17:00:00']);   // 2026-10-08 00:00:00 VN
        $this->createPost('không rõ ngày', ['posted_at' => null]);

        $range = $this->search(['date_from' => '2026-10-01', 'date_to' => '2026-10-07']);
        sort($range);
        $this->assertSame(['cuối ngày', 'đầu ngày'], $range);
        $this->assertNotContains('trước ngày', $this->search(['date_from' => '2026-10-01']));
        $this->assertNotContains('sau ngày', $this->search(['date_to' => '2026-10-07']));
        $this->assertSame(['đầu ngày'], $this->search(['date_from' => '2026-10-01', 'date_to' => '2026-10-01']));
    }

    public function test_invalid_filters_are_ignored_and_reported(): void
    {
        $this->createPost('một bài', ['posted_at' => '2026-10-05 00:00:00']);

        $this->actingAsAdmin()->get('/posts?date_from=07/10/2026&group=abc')
            ->assertOk()
            ->assertSee('Từ ngày không hợp lệ')
            ->assertSee('Hiển thị 1–1 / 1 posts');

        $this->get('/posts?date_from=2026-10-07&date_to=2026-10-01')
            ->assertOk()
            ->assertSee('phải sau hoặc bằng');

        $this->get('/posts?keyword[]=pass')->assertOk()->assertSee('Hiển thị 1–1 / 1 posts');
    }

    public function test_spec_example_pass_or_ban(): void
    {
        $this->createPost('Pass lại xe đẩy cho bé');
        $this->createPost('Bán xe đẩy cho bé');
        $this->createPost('Pass lại đồ, bán giá rẻ');
        $this->createPost('Cho tặng xe đẩy');

        $matches = $this->search(['keyword' => 'pass, bán']);
        sort($matches);

        $this->assertSame(['Bán xe đẩy cho bé', 'Pass lại xe đẩy cho bé', 'Pass lại đồ, bán giá rẻ'], $matches);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function keywordCases(): array
    {
        return [
            'uppercase keyword' => ['PASS, BÁN', ['Bán xe đẩy', 'PASS GẤP', 'pass lại']],
            'lowercase keyword, uppercase content' => ['pass gấp', ['PASS GẤP']],
            'accented keyword does not match other accents' => ['bán', ['Bán xe đẩy']],
            'unaccented keyword does not match accented words' => ['ban', ['ban công']],
            'phrase must be contiguous' => ['xe đẩy', ['Bán xe đẩy']],
            'many keywords' => ['tặng, công, gấp, không-có', ['ban công', 'Cho tặng nôi', 'PASS GẤP']],
            'empty keyword means no filter' => [' , ,', ['Bán xe đẩy', 'Cho tặng nôi', 'PASS GẤP', 'ban công', 'bạn ơi', 'giảm 50% còn 500k', 'mã a_b', 'pass lại', "it's ok", 'xe cho bé đẩy']],
            'percent is literal' => ['50%', ['giảm 50% còn 500k']],
            'underscore is literal' => ['a_b', ['mã a_b']],
            'quote does not break the query' => ["it's", ["it's ok"]],
            'backslash does not break the query' => ['\\', []],
        ];
    }

    #[DataProvider('keywordCases')]
    public function test_keyword_search(string $keyword, array $expected): void
    {
        foreach (['Bán xe đẩy', 'xe cho bé đẩy', 'PASS GẤP', 'pass lại', 'ban công', 'bạn ơi', 'Cho tặng nôi', 'giảm 50% còn 500k', 'mã a_b', "it's ok"] as $content) {
            $this->createPost($content);
        }

        $matches = $this->search(['keyword' => $keyword]);
        sort($matches);
        sort($expected);

        $this->assertSame($expected, $matches);
    }

    public function test_keywords_are_or_ed_only_among_themselves(): void
    {
        $other = FacebookGroup::factory()->create();
        $this->createPost('pass xe', ['posted_at' => '2026-10-05 00:00:00']);
        $this->createPost('bán nôi', ['posted_at' => '2026-09-01 00:00:00']);
        FacebookPost::factory()->for($other, 'group')->create(['content' => 'pass ở group khác', 'posted_at' => '2026-10-05 00:00:00']);

        // (pass OR bán) AND group AND date — not pass OR (bán AND group AND date).
        $this->assertSame(['pass xe'], $this->search([
            'keyword' => 'pass, bán',
            'group' => $this->group->id,
            'date_from' => '2026-10-01',
        ]));
    }

    public function test_too_many_keywords_are_capped(): void
    {
        $this->createPost('x21y');
        $keywords = implode(',', array_map(fn ($i) => "x{$i}y", range(1, 21)));

        $response = $this->actingAsAdmin()->get(route('posts.index', ['keyword' => $keywords]))->assertOk();

        $response->assertSee('Chỉ dùng 20 keyword đầu tiên.');
        $this->assertCount(0, $response->viewData('posts'));
    }

    public function test_posts_of_deleted_groups_are_still_listed(): void
    {
        $this->createPost('bài cũ');
        $this->group->delete();

        $this->actingAsAdmin()->get('/posts')->assertOk()->assertSee('bài cũ')->assertSee('đã xoá');
        $this->assertSame(['bài cũ'], $this->search(['group' => $this->group->id]));
    }

    public function test_content_is_escaped(): void
    {
        $this->createPost('<script>alert("xss")</script><img src=x onerror=alert(1)>', [
            'author_name' => '<b>tác giả</b>',
        ]);

        $this->actingAsAdmin()->get('/posts')
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('<b>tác giả</b>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_listing_runs_a_constant_number_of_queries(): void
    {
        $groups = FacebookGroup::factory()->count(5)->create();
        foreach ($groups as $group) {
            FacebookPost::factory()->count(10)->for($group, 'group')->create();
        }

        $this->actingAsAdmin();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/posts?keyword=a,b&date_from=2026-01-01')->assertOk();

        // count + page + eager-loaded groups + group dropdown (+ session handling); no N+1.
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
    }

    public function test_feed_shows_images_below_the_content(): void
    {
        $urls = array_map(fn ($i) => "https://scontent.x.fbcdn.net/v/{$i}.jpg?oh=a&oe=b", range(1, 6));
        $this->createPost('Pass lô đồ sơ sinh', ['image_urls' => $urls, 'post_url' => 'https://www.facebook.com/groups/1/posts/9/']);
        $this->createPost('Bài chỉ có chữ', ['image_urls' => null]);

        $response = $this->actingAsAdmin()->get('/posts')->assertOk();

        $response->assertSeeInOrder(['Pass lô đồ sơ sinh', 'src="https://scontent.x.fbcdn.net/v/1.jpg?oh=a&amp;oe=b"'], false)
            ->assertSee('referrerpolicy="no-referrer"', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('+2')            // 6 images: 4 tiles, the last one shows +2
            ->assertSee('6 ảnh')
            ->assertDontSee('v/5.jpg', false);
        $this->assertSame(4, substr_count($response->getContent(), '<img '));
    }

    public function test_long_content_is_collapsed(): void
    {
        $this->createPost(str_repeat('a', 600).'PHẦN-SAU');

        $this->actingAsAdmin()->get('/posts')->assertOk()->assertSee('Xem thêm')->assertSee('PHẦN-SAU');
    }
}

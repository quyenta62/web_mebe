<?php

namespace Tests\Feature;

use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use App\Services\Crawler\PostImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opening a post on Facebook from the tool marks it "Đã check". */
class PostCheckedTest extends TestCase
{
    use RefreshDatabase;

    private FacebookPost $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = FacebookPost::factory()->create([
            'post_url' => 'https://www.facebook.com/groups/123/posts/456/',
            'posted_at' => '2026-10-08 02:00:00',
            'updated_at' => '2026-10-08 03:00:00',
        ]);
    }

    public function test_opening_marks_the_post_and_redirects_to_facebook(): void
    {
        $this->travelTo('2026-10-09 01:15:00');

        $this->get(route('posts.open', $this->post))
            ->assertRedirect('https://www.facebook.com/groups/123/posts/456/');

        $post = $this->post->fresh();
        $this->assertSame('2026-10-09 01:15:00', $post->checked_at->toDateTimeString());
        $this->assertSame('2026-10-08 03:00:00', $post->updated_at->toDateTimeString(), 'crawler data timestamp untouched');
    }

    public function test_the_first_check_time_is_kept(): void
    {
        $this->travelTo('2026-10-09 01:15:00');
        $this->get(route('posts.open', $this->post));
        $this->travelTo('2026-10-09 05:00:00');
        $this->get(route('posts.open', $this->post))->assertRedirect();

        $this->assertSame('2026-10-09 01:15:00', $this->post->fresh()->checked_at->toDateTimeString());
    }

    public function test_only_the_posts_own_facebook_url_is_followed(): void
    {
        $noUrl = FacebookPost::factory()->create(['post_url' => null]);
        $foreign = FacebookPost::factory()->create(['post_url' => 'https://evil.example/phish']);

        $this->get(route('posts.open', $noUrl))->assertNotFound();
        $this->get(route('posts.open', $foreign))->assertNotFound();
        $this->get('/posts/999999/open')->assertNotFound();
        $this->assertNull($foreign->fresh()->checked_at);
    }

    public function test_label_is_shown_next_to_the_date_only_for_checked_posts(): void
    {
        $checked = FacebookPost::factory()->create(['content' => 'BAI-DA-CHECK', 'checked_at' => '2026-10-09 01:15:00', 'posted_at' => '2026-10-09 00:00:00']);

        $html = $this->get('/posts')->assertOk()->getContent();

        // Checked post: visible label with the first check time (Vietnam time).
        $this->assertMatchesRegularExpression('/class="checked-badge badge text-bg-success ms-1 "\s+title="Mở lần đầu lúc 08:15:00 09\/10\/2026">✓ Đã check/', $html);
        // Unchecked post: label present but hidden, revealed in this tab when a link is clicked.
        $this->assertMatchesRegularExpression('/class="checked-badge badge text-bg-success ms-1  d-none "/', $html);
        $this->assertStringContainsString('onclick="this.closest(&#039;article&#039;).querySelector(&#039;.checked-badge&#039;).classList.remove(&#039;d-none&#039;)"', $html);
        $this->assertSame(2, substr_count($html, '✓ Đã check'));
        $this->assertStringContainsString('BAI-DA-CHECK', $html);
    }

    public function test_every_facebook_link_on_the_card_goes_through_the_tracker(): void
    {
        $this->post->update(['image_urls' => ['https://scontent.x.fbcdn.net/a.jpg']]);

        $html = $this->get('/posts')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'href="'.route('posts.open', $this->post).'"'), 'date, image and Open Facebook');
        $this->assertStringNotContainsString('href="https://www.facebook.com/groups/123/posts/456/"', $html);
    }

    public function test_recrawling_keeps_the_check(): void
    {
        $this->post->forceFill(['checked_at' => '2026-10-09 01:15:00'])->save();
        $group = FacebookGroup::find($this->post->facebook_group_id);

        app(PostImporter::class)->import($group, [[
            'post_id' => $group->facebook_group_id.'_'.$this->post->facebook_post_id,
            'group_id' => $group->facebook_group_id,
            'content' => 'Nội dung mới',
            'post_url' => 'https://www.facebook.com/groups/123/posts/456/',
        ]]);

        $post = $this->post->fresh();
        $this->assertSame('Nội dung mới', $post->content);
        $this->assertSame('2026-10-09 01:15:00', $post->checked_at->toDateTimeString());
    }

    public function test_pagination_is_shown_above_and_below_the_list(): void
    {
        FacebookPost::factory()->count(60)->create();

        $html = $this->get('/posts')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '<nav aria-label="Phân trang">'));
        $this->assertLessThan(strpos($html, '<article'), strpos($html, '<nav aria-label="Phân trang">'), 'first pagination sits above the posts');
        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringContainsString('page=2', $html);
    }
}

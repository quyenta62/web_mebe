<?php

namespace Tests\Feature\Queue;

use App\Enums\CrawlRunStatus;
use App\Jobs\CrawlFacebookGroupJob;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlAllButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_button_shows_the_number_of_active_groups(): void
    {
        FacebookGroup::factory()->count(2)->create();
        FacebookGroup::factory()->inactive()->create();

        $this->get('/groups')
            ->assertOk()
            ->assertSee('Crawl all (2 group active)')
            ->assertSee('action="'.route('groups.crawl-all').'"', false);
    }

    public function test_button_is_disabled_without_active_groups(): void
    {
        FacebookGroup::factory()->inactive()->create();

        $this->get('/groups')->assertSee('title="Không có group active"', false);
        $this->post('/groups/crawl-all', ['max_posts' => 50])->assertSessionHas('status', 'Không có group active nào để crawl.');
        Queue::assertNothingPushed();
    }

    public function test_queues_every_active_group_with_the_chosen_count(): void
    {
        $active = FacebookGroup::factory()->count(3)->create();
        FacebookGroup::factory()->inactive()->create();
        FacebookGroup::factory()->create()->delete();
        // One active group is already being crawled: it is skipped, not queued twice.
        CrawlRun::factory()->for($active[1], 'group')->create(['status' => CrawlRunStatus::Running]);

        $this->post('/groups/crawl-all', ['max_posts' => 100])
            ->assertRedirect('/groups')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'crawl 100 bài mới nhất cho 2/3 group active')
                && str_contains($m, 'bỏ qua 1 group'));

        Queue::assertPushed(CrawlFacebookGroupJob::class, 2);
        $queued = CrawlRun::where('status', CrawlRunStatus::Pending)->get();
        $this->assertEqualsCanonicalizing([$active[0]->id, $active[2]->id], $queued->pluck('facebook_group_id')->all());
        $this->assertSame([100], $queued->pluck('max_posts')->unique()->values()->all());

        // Every queued group is announced when it finishes.
        $queued->each->update(['status' => CrawlRunStatus::Success, 'posts_found' => 90, 'posts_created' => 5, 'finished_at' => now()]);
        $this->assertSame(2, substr_count($this->get('/groups')->getContent(), 'Crawl xong'));
    }

    public function test_invalid_count_is_rejected(): void
    {
        FacebookGroup::factory()->create();

        $this->from('/groups')->post('/groups/crawl-all', ['max_posts' => 500])
            ->assertSessionHasErrors('max_posts');
        Queue::assertNothingPushed();
    }

    public function test_command_accepts_a_post_count(): void
    {
        FacebookGroup::factory()->count(2)->create();

        $this->artisan('facebook:crawl-all', ['--posts' => '30'])->assertSuccessful();
        $this->assertSame([30], CrawlRun::pluck('max_posts')->unique()->values()->all());

        $this->artisan('facebook:crawl-all', ['--posts' => '0'])->assertExitCode(2);
    }
}

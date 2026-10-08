<?php

namespace Tests\Feature;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlRunsPageTest extends TestCase
{
    use RefreshDatabase;

    private FacebookGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = FacebookGroup::factory()->create(['name' => 'Hội mẹ bỉm', 'facebook_group_id' => '123456789']);
    }

    public function test_lists_runs_newest_first_with_all_columns(): void
    {
        $this->travelTo('2026-10-08 02:00:00'); // 09:00 in Vietnam
        $old = CrawlRun::factory()->for($this->group, 'group')->succeeded(43, 12)->create([
            'max_posts' => 50,
            'started_at' => '2026-10-08 01:58:00',
            'finished_at' => '2026-10-08 01:59:05',
        ]);
        $new = CrawlRun::factory()->for($this->group, 'group')->failed('LOGIN_REQUIRED: Facebook session is expired')->create();
        CrawlRun::factory()->for($this->group, 'group')->create(['status' => CrawlRunStatus::Running, 'started_at' => now()]);

        $response = $this->get('/crawl-runs')->assertOk();

        $response->assertSeeInOrder(['Group', 'Started', 'Finished', 'Status', 'Posts found', 'Posts created', 'Error'])
            ->assertSeeInOrder(['Running', "#{$new->id}", 'Failed', 'LOGIN_REQUIRED: Facebook session is expired', "#{$old->id}", 'Success'])
            ->assertSee('Hội mẹ bỉm')
            ->assertSee('2026-10-08 08:58:00')   // started, Vietnam time
            ->assertSee('2026-10-08 08:59:05')   // finished
            ->assertSee('43<span class="text-secondary">/50</span>', false)
            ->assertSee('Hiển thị 1–3 / 3 crawl runs');
    }

    public function test_long_errors_are_shortened_in_the_list_and_link_to_the_detail(): void
    {
        $run = CrawlRun::factory()->for($this->group, 'group')->failed('CRAWLER_ERROR: '.str_repeat('x', 300).'END-OF-ERROR')->create();

        $this->get('/crawl-runs')
            ->assertOk()
            ->assertDontSee('END-OF-ERROR')
            ->assertSee(route('crawl-runs.show', $run), false);
    }

    public function test_list_is_paginated(): void
    {
        CrawlRun::factory()->count(51)->for($this->group, 'group')->create();

        $this->get('/crawl-runs')->assertSee('Hiển thị 1–50 / 51 crawl runs');
        $this->get('/crawl-runs?page=2')->assertSee('Hiển thị 51–51 / 51 crawl runs');
    }

    public function test_empty_state(): void
    {
        $this->get('/crawl-runs')->assertOk()->assertSee('Chưa có lần crawl nào.');
    }

    public function test_detail_shows_the_full_error_and_run_data(): void
    {
        $error = 'CHECKPOINT: Facebook redirected to a security checkpoint (https://www.facebook.com/checkpoint/)'
            .str_repeat(' detail', 40).' END-OF-ERROR';
        $run = CrawlRun::factory()->for($this->group, 'group')->failed($error)->create([
            'max_posts' => 100,
            'posts_found' => 7,
            'posts_created' => 3,
            'started_at' => '2026-10-08 01:00:00',
            'finished_at' => '2026-10-08 01:01:30',
        ]);

        $this->get("/crawl-runs/{$run->id}")
            ->assertOk()
            ->assertSee("Crawl run #{$run->id}")
            ->assertSee('Failed')
            ->assertSee('<code>CHECKPOINT</code>', false)
            ->assertSee('END-OF-ERROR')
            ->assertSee('Hội mẹ bỉm')
            ->assertSee('123456789')
            ->assertSee('100')
            ->assertSee('90 giây')
            ->assertSee(route('posts.index', ['group' => $this->group->id]), false);
    }

    public function test_detail_of_a_successful_run_has_no_error_block(): void
    {
        $run = CrawlRun::factory()->for($this->group, 'group')->succeeded()->create();

        $this->get("/crawl-runs/{$run->id}")->assertOk()->assertSee('Success')->assertDontSee('border-danger', false);
    }

    public function test_unknown_run_is_not_found(): void
    {
        $this->get('/crawl-runs/999999')->assertNotFound();
    }

    public function test_runs_of_deleted_groups_are_kept(): void
    {
        $run = CrawlRun::factory()->for($this->group, 'group')->succeeded()->create();
        $this->group->delete();

        $this->get('/crawl-runs')->assertOk()->assertSee('Hội mẹ bỉm')->assertSee('đã xoá');
        $this->get("/crawl-runs/{$run->id}")->assertOk()->assertSee('đã xoá');
    }

    public function test_error_messages_are_escaped(): void
    {
        $run = CrawlRun::factory()->for($this->group, 'group')->failed('CRAWLER_ERROR: <script>alert(1)</script>')->create();

        $this->get('/crawl-runs')->assertDontSee('<script>alert(1)</script>', false);
        $this->get("/crawl-runs/{$run->id}")->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_failed_badge_on_groups_and_failure_notice_link_to_the_detail(): void
    {
        Queue::fake();
        $this->post("/groups/{$this->group->id}/crawl", ['max_posts' => 20]);
        $run = CrawlRun::sole();
        $run->update(['status' => CrawlRunStatus::Failed, 'error_message' => 'LOGIN_REQUIRED: expired', 'finished_at' => now()]);

        $this->get('/groups')
            ->assertSee('Crawl thất bại')
            ->assertSee('Lỗi lần gần nhất')
            ->assertSee('href="'.route('crawl-runs.show', $run).'"', false);
    }

    public function test_listing_runs_a_constant_number_of_queries(): void
    {
        foreach (FacebookGroup::factory()->count(5)->create() as $group) {
            CrawlRun::factory()->count(5)->for($group, 'group')->succeeded()->create();
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/crawl-runs')->assertOk();

        // count + page + eager-loaded groups; no query per run.
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
    }

    public function test_navigation_links_to_crawl_runs(): void
    {
        $this->get('/groups')->assertSee('href="'.route('crawl-runs.index').'"', false);
    }
}

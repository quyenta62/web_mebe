<?php

namespace Tests\Feature;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestCrawlHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_end_of_the_latest_successful_crawl_on_every_page(): void
    {
        [$a, $b] = FacebookGroup::factory()->count(2)->create();
        CrawlRun::factory()->for($a, 'group')->succeeded()->create(['finished_at' => '2026-10-08 01:00:00']);
        CrawlRun::factory()->for($b, 'group')->succeeded()->create(['finished_at' => '2026-10-08 02:32:37']); // 09:32:37 VN
        // A later failed or unfinished crawl does not count: the data was not refreshed.
        CrawlRun::factory()->for($a, 'group')->failed()->create(['finished_at' => '2026-10-08 05:00:00']);
        CrawlRun::factory()->for($b, 'group')->create(['status' => CrawlRunStatus::Running, 'started_at' => '2026-10-08 06:00:00']);

        foreach (['/groups', '/posts', '/crawl-runs'] as $path) {
            $this->get($path)->assertOk()
                ->assertSeeInOrder(['Data mới nhất:', '09:32:37 08/10/2026'])
                ->assertSee('class="latest-crawl', false);
        }
    }

    public function test_runs_of_deleted_groups_still_count(): void
    {
        $group = FacebookGroup::factory()->create();
        CrawlRun::factory()->for($group, 'group')->succeeded()->create(['finished_at' => '2026-10-08 02:32:37']);
        $group->delete();

        $this->get('/posts')->assertSee('09:32:37 08/10/2026');
    }

    public function test_before_any_crawl(): void
    {
        $this->get('/posts')->assertOk()->assertSee('Chưa crawl lần nào')->assertDontSee('Data mới nhất:');
    }
}

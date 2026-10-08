<?php

namespace Tests\Feature\Database;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_has_many_posts_and_crawl_runs(): void
    {
        $group = FacebookGroup::factory()->create();
        FacebookPost::factory()->count(3)->for($group, 'group')->create();
        CrawlRun::factory()->count(2)->for($group, 'group')->create();

        $this->assertCount(3, $group->posts);
        $this->assertCount(2, $group->crawlRuns);
        $this->assertTrue($group->posts->first()->group->is($group));
        $this->assertTrue($group->crawlRuns->first()->group->is($group));
    }

    public function test_facebook_group_id_is_unique(): void
    {
        FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);

        $this->expectException(UniqueConstraintViolationException::class);
        FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);
    }

    public function test_post_is_unique_per_group(): void
    {
        [$groupA, $groupB] = FacebookGroup::factory()->count(2)->create();
        FacebookPost::factory()->for($groupA, 'group')->create(['facebook_post_id' => '987654321']);

        // The same Facebook post ID in another group is a different row.
        FacebookPost::factory()->for($groupB, 'group')->create(['facebook_post_id' => '987654321']);
        $this->assertSame(2, FacebookPost::count());

        $this->expectException(UniqueConstraintViolationException::class);
        FacebookPost::factory()->for($groupA, 'group')->create(['facebook_post_id' => '987654321']);
    }

    public function test_deleting_a_group_keeps_its_posts_and_runs(): void
    {
        $group = FacebookGroup::factory()->create();
        $post = FacebookPost::factory()->for($group, 'group')->create();
        CrawlRun::factory()->for($group, 'group')->create();

        $group->delete();

        $this->assertSoftDeleted($group);
        $this->assertSame(1, FacebookPost::count());
        $this->assertSame(1, CrawlRun::count());
        // History still resolves the (deleted) group.
        $this->assertTrue($post->fresh()->group->is($group));
    }

    public function test_database_refuses_hard_delete_of_a_group_with_posts(): void
    {
        $group = FacebookGroup::factory()->create();
        FacebookPost::factory()->for($group, 'group')->create();

        $this->expectException(QueryException::class);
        $group->forceDelete();
    }

    public function test_crawl_run_defaults_to_pending_and_casts_status(): void
    {
        $run = CrawlRun::create(['facebook_group_id' => FacebookGroup::factory()->create()->id]);

        $this->assertSame(CrawlRunStatus::Pending, $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->posts_found);

        $run->update(['status' => CrawlRunStatus::Failed, 'error_message' => 'CHECKPOINT']);
        $this->assertDatabaseHas('crawl_runs', ['id' => $run->id, 'status' => 'failed']);
    }

    public function test_active_scope_returns_only_enabled_groups(): void
    {
        $active = FacebookGroup::factory()->create();
        FacebookGroup::factory()->inactive()->create();

        $this->assertEquals([$active->id], FacebookGroup::active()->pluck('id')->all());
    }

    public function test_content_comparison_is_case_insensitive_and_accent_sensitive(): void
    {
        $group = FacebookGroup::factory()->create();
        foreach (['Bán xe đẩy', 'bạn ơi cho hỏi', 'ban công', 'ĐỒ CHO BÉ'] as $content) {
            FacebookPost::factory()->for($group, 'group')->create(['content' => $content]);
        }

        $matches = fn (string $term) => FacebookPost::where('content', 'like', "%{$term}%")->pluck('content')->all();

        $this->assertSame(['Bán xe đẩy'], $matches('BÁN'));
        $this->assertSame(['ĐỒ CHO BÉ'], $matches('đồ cho bé'));
        $this->assertSame(['ban công'], $matches('ban'));
    }

    public function test_expected_indexes_exist(): void
    {
        $indexes = collect(Schema::getIndexes('facebook_posts'))->map(fn ($i) => [$i['columns'], $i['unique']]);

        $this->assertContains([['facebook_group_id', 'facebook_post_id'], true], $indexes);
        $this->assertContains([['facebook_group_id', 'posted_at'], false], $indexes);
        $this->assertContains([['posted_at'], false], $indexes);
        $this->assertContains(
            [['facebook_group_id'], true],
            collect(Schema::getIndexes('facebook_groups'))->map(fn ($i) => [$i['columns'], $i['unique']]),
        );
    }
}

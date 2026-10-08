<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** On phones, list tables turn into one card per row, labelled from data-label (layouts.app CSS). */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_is_mobile_ready(): void
    {
        $this->get('/posts')
            ->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false)
            ->assertSee('@media (max-width: 767.98px)', false)
            ->assertSee('FB Monitor'); // short brand on phones
    }

    public function test_groups_table_cells_carry_labels(): void
    {
        FacebookGroup::factory()->create();

        $html = $this->get('/groups')->assertOk()->assertSee('table-stack', false)->getContent();

        foreach (['Group ID', 'Tên', 'URL', 'Trạng thái', 'Posts', 'Crawl gần nhất'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html);
        }
        $this->assertStringContainsString('stack-actions', $html);
    }

    public function test_crawl_runs_table_cells_carry_labels(): void
    {
        CrawlRun::factory()->succeeded()->create();

        $html = $this->get('/crawl-runs')->assertOk()->assertSee('table-stack', false)->getContent();

        foreach (['Crawl run', 'Group', 'Started', 'Finished', 'Status', 'Posts found', 'Posts created', 'Error'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html);
        }
    }
}

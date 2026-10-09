<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NightModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_uses_the_dark_theme_without_hard_coded_light_backgrounds(): void
    {
        $group = FacebookGroup::factory()->create();
        FacebookPost::factory()->for($group, 'group')->create(['image_urls' => ['https://scontent.x.fbcdn.net/a.jpg']]);
        $run = CrawlRun::factory()->for($group, 'group')->failed()->create();

        foreach (['/groups', '/groups/create', "/groups/{$group->id}/edit", '/posts?keyword=pass', '/crawl-runs', "/crawl-runs/{$run->id}"] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<html lang="vi" data-bs-theme="dark">', $html, $path);
            $this->assertStringContainsString('<meta name="color-scheme" content="dark">', $html, $path);
            foreach (['bg-white', 'bg-light', 'table-light', 'text-bg-light'] as $lightClass) {
                $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b'.$lightClass.'\b/', $html, "{$lightClass} on {$path}");
            }
        }
    }

    public function test_muted_text_and_borders_are_lifted_for_contrast(): void
    {
        $html = $this->get('/posts')->assertOk()->getContent();

        // Measured in a browser: muted text 8-10:1 (was 2.8:1), borders >= 3:1 (was 1.6:1).
        $this->assertStringContainsString('--bs-secondary-color: #c3cad1;', $html);
        $this->assertStringContainsString('--bs-border-color: #868e96;', $html);
        $this->assertStringContainsString('[data-bs-theme=dark] .text-secondary { color: var(--bs-secondary-color) !important; }', $html);
    }

    public function test_back_to_top_button_is_on_every_page(): void
    {
        foreach (['/groups', '/posts', '/crawl-runs'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<a href="#top" class="scroll-top', false)
                ->assertSee('aria-label="Lên đầu trang"', false);
        }
    }
}

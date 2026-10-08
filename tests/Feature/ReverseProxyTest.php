<?php

namespace Tests\Feature;

use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Access through `tailscale serve`: HTTPS ends at Tailscale, which forwards plain HTTP to the app. */
class ReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    private const FORWARDED = [
        'X-Forwarded-For' => '100.101.102.103',
        'X-Forwarded-Host' => 'fb-monitor.example.ts.net',
        'X-Forwarded-Proto' => 'https',
    ];

    public function test_redirects_keep_the_tailscale_https_address(): void
    {
        $this->withHeaders(self::FORWARDED)->get('/')
            ->assertRedirect('https://fb-monitor.example.ts.net/groups');
    }

    public function test_links_and_forms_use_the_tailscale_https_address(): void
    {
        $group = FacebookGroup::factory()->create();

        $this->withHeaders(self::FORWARDED)->get('/groups')
            ->assertSee('href="https://fb-monitor.example.ts.net/posts"', false)
            ->assertSee('action="https://fb-monitor.example.ts.net/groups/'.$group->id.'/crawl"', false);
    }

    public function test_direct_local_access_still_works(): void
    {
        $this->get('/')->assertRedirect('/groups');

        $html = $this->get('/groups')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.url('/posts').'"', $html);
        $this->assertStringStartsWith('http://', url('/posts'));
        $this->assertStringNotContainsString('ts.net', $html);
    }
}

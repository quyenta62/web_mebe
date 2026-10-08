<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Single-user local tool: no login. This is only safe while the app is reachable
 * from this machine alone, so the port binding is checked too.
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_open_without_logging_in(): void
    {
        $group = FacebookGroup::factory()->create();
        $run = CrawlRun::factory()->for($group, 'group')->create();

        $this->get('/')->assertRedirect('/groups');
        foreach (['/groups', '/groups/create', "/groups/{$group->id}/edit", '/posts', '/crawl-runs', "/crawl-runs/{$run->id}"] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/groups')->assertDontSee('Đăng xuất')->assertSee('Crawl Runs');
    }

    public function test_login_pages_no_longer_exist(): void
    {
        $this->get('/login')->assertNotFound();
        $this->post('/logout')->assertNotFound();
    }

    public function test_forms_still_carry_a_csrf_token(): void
    {
        FacebookGroup::factory()->create();

        $this->get('/groups')->assertSee('name="_token"', false);
        $this->get('/groups/create')->assertSee('name="_token"', false);
    }

    public function test_docker_publishes_ports_on_localhost_only(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        preg_match_all('/ports:\s*\n((?:\s*-\s*"[^"]+"\s*\n)+)/', $compose, $blocks);
        $ports = [];
        foreach ($blocks[1] as $block) {
            preg_match_all('/"([^"]+)"/', $block, $matches);
            $ports = array_merge($ports, $matches[1]);
        }

        $this->assertNotEmpty($ports);
        foreach ($ports as $port) {
            $this->assertStringStartsWith('127.0.0.1:', $port, "Port {$port} would expose the login-free app beyond this machine.");
        }
    }
}

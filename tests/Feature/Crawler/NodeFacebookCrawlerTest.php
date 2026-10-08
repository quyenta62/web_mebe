<?php

namespace Tests\Feature\Crawler;

use App\Services\Crawler\CrawlLimits;
use App\Services\Crawler\NodeFacebookCrawler;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Tests\TestCase;

class NodeFacebookCrawlerTest extends TestCase
{
    private string $crawlerPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawlerPath = sys_get_temp_dir().'/fake-crawler-'.uniqid();
        File::ensureDirectoryExists($this->crawlerPath.'/dist');
        File::put($this->crawlerPath.'/dist/index.js', '');
        config([
            'crawler.path' => $this->crawlerPath,
            'crawler.node_binary' => 'node',
            'crawler.max_posts' => 40,
            'crawler.max_scrolls' => 10,
            'crawler.timeout_seconds' => 120,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->crawlerPath);
        parent::tearDown();
    }

    /** Fakes the node process: writes $posts to the --output file and exits with $exitCode. */
    private function fakeCrawler(array $posts, int $exitCode = 0, string $stderr = ''): void
    {
        Process::fake(function (PendingProcess $process) use ($posts, $exitCode, $stderr) {
            $args = $process->command;
            file_put_contents($args[array_search('--output', $args, true) + 1], json_encode($posts));

            return Process::result(output: 'Crawl completed.', errorOutput: $stderr, exitCode: $exitCode);
        });
    }

    public function test_runs_the_crawler_with_fixed_arguments_and_no_shell(): void
    {
        $this->fakeCrawler([['post_id' => '123456789_1']]);

        $result = (new NodeFacebookCrawler)->crawl('123456789', CrawlLimits::forPosts(40));

        $this->assertTrue($result->successful);
        $this->assertSame([['post_id' => '123456789_1']], $result->posts);

        Process::assertRan(function (PendingProcess $process) {
            $output = $process->command[array_search('--output', $process->command, true) + 1];

            return is_array($process->command) // array = executed without a shell
                && $process->path === $this->crawlerPath
                && $process->timeout === 264 // crawl timeout 204 s + 60 s
                && $process->environment['HEADLESS'] === 'true'
                && array_slice($process->command, 0, 10) === [
                    'node', 'dist/index.js', '123456789', '--headless',
                    '--max-posts', '40', '--max-scrolls', '18', '--timeout', '204000',
                ]
                && str_starts_with($output, storage_path('app/crawler/'))
                && ! file_exists($output); // the temp output is removed after reading
        });
    }

    public function test_maps_exit_codes_and_reads_the_error_from_stderr(): void
    {
        $stderr = "[2026-10-07T09:27:40.080Z] WARN  Facebook shows a login dialog\n"
            ."[2026-10-07T09:27:40.082Z] ERROR LOGIN_REQUIRED: Facebook showed a login dialog after 1 post(s)\n"
            ."\nRun `npm run login` to create or refresh the Facebook session.\n";
        $this->fakeCrawler([['post_id' => '123456789_1']], 3, $stderr);

        $result = (new NodeFacebookCrawler)->crawl('123456789', CrawlLimits::forPosts(40));

        $this->assertFalse($result->successful);
        $this->assertSame('LOGIN_REQUIRED', $result->errorCode);
        $this->assertSame('Facebook showed a login dialog after 1 post(s)', $result->errorMessage);
        $this->assertCount(1, $result->posts, 'partial posts are returned');
    }

    public function test_unknown_exit_code_without_message(): void
    {
        $this->fakeCrawler([], 1);

        $result = (new NodeFacebookCrawler)->crawl('123456789', CrawlLimits::forPosts(40));

        $this->assertSame('CRAWLER_ERROR', $result->errorCode);
        $this->assertSame('Crawler thoát với exit code 1.', $result->errorMessage);
    }

    public function test_invalid_json_output_yields_no_posts(): void
    {
        Process::fake(function (PendingProcess $process) {
            file_put_contents($process->command[array_search('--output', $process->command, true) + 1], '{not json');

            return Process::result(exitCode: 0);
        });

        $this->assertSame([], (new NodeFacebookCrawler)->crawl('123456789', CrawlLimits::forPosts(40))->posts);
    }

    public function test_missing_build_fails_without_running_anything(): void
    {
        Process::fake();
        File::delete($this->crawlerPath.'/dist/index.js');

        $result = (new NodeFacebookCrawler)->crawl('123456789', CrawlLimits::forPosts(40));

        $this->assertSame('CRAWLER_NOT_BUILT', $result->errorCode);
        Process::assertNothingRan();
    }

    public function test_rejects_non_numeric_group_ids(): void
    {
        Process::fake();

        $this->expectException(InvalidArgumentException::class);
        (new NodeFacebookCrawler)->crawl('123456789 --output /etc/passwd', CrawlLimits::forPosts(40));
    }
}

<?php

namespace Tests\Unit;

use App\Services\Crawler\CrawlLimits;
use InvalidArgumentException;
use Tests\TestCase;

class CrawlLimitsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['crawler.max_posts' => 50, 'crawler.max_scrolls' => 15, 'crawler.timeout_seconds' => 300]);
    }

    public function test_more_posts_get_more_scrolls_and_time(): void
    {
        $small = CrawlLimits::forPosts(20);
        $this->assertSame([20, 15, 300], [$small->maxPosts, $small->maxScrolls, $small->timeoutSeconds]);

        $large = CrawlLimits::forPosts(200);
        $this->assertSame([200, 71, 628], [$large->maxPosts, $large->maxScrolls, $large->timeoutSeconds]);
        $this->assertSame(628, CrawlLimits::longestTimeoutSeconds());
    }

    public function test_default_uses_config(): void
    {
        $this->assertSame(50, CrawlLimits::forPosts()->maxPosts);
    }

    public function test_stays_within_crawler_bounds(): void
    {
        config(['crawler.timeout_seconds' => 5000, 'crawler.max_scrolls' => 500]);
        $limits = CrawlLimits::forPosts(200);

        $this->assertSame(100, $limits->maxScrolls);
        $this->assertSame(1800, $limits->timeoutSeconds);
    }

    public function test_rejects_out_of_range_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CrawlLimits::forPosts(201);
    }
}

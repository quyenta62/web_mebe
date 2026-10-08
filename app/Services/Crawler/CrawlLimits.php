<?php

namespace App\Services\Crawler;

use InvalidArgumentException;

/**
 * Limits for one crawl, derived from the number of recent posts requested.
 * Facebook loads roughly 3 posts per scroll, so more posts need more scrolls
 * and more time. Crawler bounds: posts 1-200, scrolls 0-100, timeout 10-1800 s.
 */
final class CrawlLimits
{
    public const MIN_POSTS = 1;

    public const MAX_POSTS = 200;

    public const MAX_SCROLLS = 100;

    public const MAX_TIMEOUT_SECONDS = 1800;

    private function __construct(
        public readonly int $maxPosts,
        public readonly int $maxScrolls,
        public readonly int $timeoutSeconds,
    ) {}

    public static function forPosts(?int $posts = null): self
    {
        $posts ??= (int) config('crawler.max_posts');
        if ($posts < self::MIN_POSTS || $posts > self::MAX_POSTS) {
            throw new InvalidArgumentException('Số bài phải từ '.self::MIN_POSTS.' đến '.self::MAX_POSTS.'.');
        }

        $scrolls = min(self::MAX_SCROLLS, max((int) config('crawler.max_scrolls'), intdiv($posts, 3) + 5));
        // About 8 s per scroll (delay + loading) plus 60 s to open the group.
        $timeout = min(self::MAX_TIMEOUT_SECONDS, max((int) config('crawler.timeout_seconds'), 60 + $scrolls * 8));

        return new self($posts, $scrolls, $timeout);
    }

    /** Upper bound of any crawl's timeout, for lock TTLs and the queue's retry_after. */
    public static function longestTimeoutSeconds(): int
    {
        return self::forPosts(self::MAX_POSTS)->timeoutSeconds;
    }
}

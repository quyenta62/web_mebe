<?php

namespace Tests\Fakes;

use App\Services\Crawler\CrawlerResult;
use App\Services\Crawler\CrawlLimits;
use App\Services\Crawler\FacebookCrawler;
use RuntimeException;

/** Stands in for the Node crawler so tests never touch Facebook. */
class FakeFacebookCrawler implements FacebookCrawler
{
    /** @var array<int, string> */
    public array $calls = [];

    private ?CrawlerResult $next = null;

    private bool $throw = false;

    public function returns(CrawlerResult $result): static
    {
        $this->next = $result;

        return $this;
    }

    public function throws(): static
    {
        $this->throw = true;

        return $this;
    }

    /** @var array<int, CrawlLimits> */
    public array $limits = [];

    public function crawl(string $facebookGroupId, CrawlLimits $limits): CrawlerResult
    {
        $this->calls[] = $facebookGroupId;
        $this->limits[] = $limits;
        if ($this->throw) {
            throw new RuntimeException('Disk full');
        }

        return $this->next ?? CrawlerResult::succeeded([]);
    }

    /** One crawler JSON row, in the Phase 1 output format. */
    public static function row(string $groupId, string $postId, array $overrides = []): array
    {
        return array_merge([
            'post_id' => "{$groupId}_{$postId}",
            'group_id' => $groupId,
            'author_name' => 'Nguyễn Văn A',
            'content' => 'Pass lại xe đẩy cho bé',
            'post_url' => "https://www.facebook.com/groups/{$groupId}/posts/{$postId}/",
            'posted_at' => '2026-10-07T10:30:00+07:00',
            'image_urls' => ["https://scontent.fhan2-1.fna.fbcdn.net/v/t39/{$postId}.jpg?oh=x&oe=1"],
        ], $overrides);
    }
}

<?php

namespace App\Services\Crawler;

interface FacebookCrawler
{
    /** Crawls the visible posts of one group. Never throws for crawler-side failures. */
    public function crawl(string $facebookGroupId, CrawlLimits $limits): CrawlerResult;
}

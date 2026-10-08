<?php

namespace App\Services\Crawler;

final class CrawlerResult
{
    /**
     * @param  array<int, mixed>  $posts  Raw rows from the crawler JSON (may be partial on failure).
     */
    private function __construct(
        public readonly bool $successful,
        public readonly array $posts,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function succeeded(array $posts): self
    {
        return new self(true, $posts);
    }

    public static function failed(string $code, string $message, array $posts = []): self
    {
        return new self(false, $posts, $code, $message);
    }

    public function error(): ?string
    {
        return $this->successful ? null : "{$this->errorCode}: {$this->errorMessage}";
    }
}

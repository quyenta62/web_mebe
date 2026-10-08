<?php

namespace App\Services\Crawler;

final class ImportResult
{
    public function __construct(
        public readonly int $found,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
    ) {}
}

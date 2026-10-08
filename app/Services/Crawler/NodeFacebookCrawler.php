<?php

namespace App\Services\Crawler;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Runs the Phase 1 crawler: `node dist/index.js <groupId> --headless ... --output <file>`.
 * Arguments are passed as an array (no shell), and the group ID is digits only.
 */
class NodeFacebookCrawler implements FacebookCrawler
{
    /** Exit codes documented in crawler_mebe/docs/CRAWLER.md. */
    private const EXIT_CODES = [
        2 => 'INVALID_INPUT',
        3 => 'LOGIN_REQUIRED',
        4 => 'CHECKPOINT',
        5 => 'GROUP_UNAVAILABLE',
        6 => 'TIMEOUT',
    ];

    /** Seconds the process may run past the crawler's own timeout before it is killed. */
    private const KILL_GRACE_SECONDS = 60;

    public function crawl(string $facebookGroupId, CrawlLimits $limits): CrawlerResult
    {
        if (! preg_match('/^\d{5,25}$/', $facebookGroupId)) {
            throw new InvalidArgumentException('Facebook group ID must be 5-25 digits.');
        }

        $crawlerPath = (string) config('crawler.path');
        if (! is_file($crawlerPath.'/dist/index.js')) {
            return CrawlerResult::failed(
                'CRAWLER_NOT_BUILT',
                "Không tìm thấy {$crawlerPath}/dist/index.js. Kiểm tra CRAWLER_PATH và chạy `npm run build` trong thư mục crawler.",
            );
        }

        $outputDir = storage_path('app/crawler');
        File::ensureDirectoryExists($outputDir);
        $outputFile = $outputDir.'/'.Str::uuid().'.json';
        $timeoutSeconds = $limits->timeoutSeconds;

        try {
            $result = Process::path($crawlerPath)
                ->env(['HEADLESS' => 'true', 'LOG_LEVEL' => 'info'])
                ->timeout($timeoutSeconds + self::KILL_GRACE_SECONDS)
                ->run([
                    (string) config('crawler.node_binary'),
                    'dist/index.js',
                    $facebookGroupId,
                    '--headless',
                    '--max-posts', (string) $limits->maxPosts,
                    '--max-scrolls', (string) $limits->maxScrolls,
                    '--timeout', (string) ($timeoutSeconds * 1000),
                    '--output', $outputFile,
                ]);
        } catch (ProcessTimedOutException) {
            return CrawlerResult::failed('TIMEOUT', "Crawler bị dừng vì chạy quá {$timeoutSeconds} giây.", $this->readOutput($outputFile));
        }

        $posts = $this->readOutput($outputFile);
        if ($result->successful()) {
            return CrawlerResult::succeeded($posts);
        }

        $code = self::EXIT_CODES[$result->exitCode()] ?? 'CRAWLER_ERROR';

        return CrawlerResult::failed(
            $code,
            $this->errorMessage($result->errorOutput()) ?? "Crawler thoát với exit code {$result->exitCode()}.",
            $posts,
        );
    }

    /** Reads and deletes the crawler JSON output; [] when missing or invalid. */
    private function readOutput(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        } finally {
            @unlink($file);
        }

        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    /** The crawler logs failures as "ERROR <CODE>: <message>" on stderr; keep the last one. */
    private function errorMessage(string $stderr): ?string
    {
        $stderr = (string) preg_replace('/\e\[[0-9;]*m/', '', $stderr);
        if (! preg_match_all('/ERROR\s+(?:[A-Z_]+:\s*)?(.+)$/m', $stderr, $matches)) {
            return null;
        }

        return Str::limit(trim(end($matches[1])), 1000);
    }
}

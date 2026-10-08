<?php

namespace App\Console\Commands;

use App\Models\FacebookGroup;
use App\Services\Crawler\CrawlDispatcher;
use App\Services\Crawler\CrawlLimits;
use Illuminate\Console\Command;

class CrawlAllFacebookGroups extends Command
{
    protected $signature = 'facebook:crawl-all
        {--posts= : Số bài mới nhất cần lấy cho mỗi group (1-200, mặc định CRAWLER_MAX_POSTS)}';

    protected $description = 'Đưa tất cả group đang active vào hàng đợi crawl (queue worker sẽ chạy lần lượt)';

    public function handle(CrawlDispatcher $dispatcher): int
    {
        $posts = $this->option('posts');
        if ($posts !== null && (! ctype_digit((string) $posts) || (int) $posts < CrawlLimits::MIN_POSTS || (int) $posts > CrawlLimits::MAX_POSTS)) {
            $this->error('--posts phải là số từ '.CrawlLimits::MIN_POSTS.' đến '.CrawlLimits::MAX_POSTS.'.');

            return self::INVALID;
        }

        $groups = FacebookGroup::active()->orderBy('id')->get();
        if ($groups->isEmpty()) {
            $this->info('Không có group active nào.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $group) {
            $run = $dispatcher->queue($group, $posts === null ? null : (int) $posts);
            $rows[] = [
                $group->facebook_group_id,
                $group->name ?? '—',
                $run ? "queued (crawl run #{$run->id})" : 'skipped (đang chờ/đang crawl)',
            ];
        }

        $this->table(['Group ID', 'Tên', 'Kết quả'], $rows);
        $queued = collect($rows)->filter(fn ($row) => str_starts_with($row[2], 'queued'))->count();
        $this->info("Đã đưa {$queued}/{$groups->count()} group vào hàng đợi '".config('crawler.queue')."'. Cần queue worker đang chạy.");

        return self::SUCCESS;
    }
}

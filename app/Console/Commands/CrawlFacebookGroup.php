<?php

namespace App\Console\Commands;

use App\Enums\CrawlRunStatus;
use App\Models\FacebookGroup;
use App\Services\Crawler\CrawlLimits;
use App\Services\Crawler\GroupCrawlService;
use Illuminate\Console\Command;
use Throwable;

class CrawlFacebookGroup extends Command
{
    protected $signature = 'facebook:crawl
        {groupId : Facebook Group ID (dãy số) của một group đã thêm ở /groups}
        {--posts= : Số bài mới nhất cần lấy (1-200, mặc định CRAWLER_MAX_POSTS)}';

    protected $description = 'Crawl các bài đang hiển thị của một Facebook group và lưu vào database';

    public function handle(GroupCrawlService $service): int
    {
        $groupId = (string) $this->argument('groupId');
        if (! preg_match('/^\d{5,25}$/', $groupId)) {
            $this->error('Facebook Group ID phải là dãy 5–25 chữ số.');

            return self::INVALID;
        }

        $posts = $this->option('posts');
        if ($posts !== null && (! ctype_digit((string) $posts) || (int) $posts < CrawlLimits::MIN_POSTS || (int) $posts > CrawlLimits::MAX_POSTS)) {
            $this->error('--posts phải là số từ '.CrawlLimits::MIN_POSTS.' đến '.CrawlLimits::MAX_POSTS.'.');

            return self::INVALID;
        }
        $limits = CrawlLimits::forPosts($posts === null ? null : (int) $posts);

        $group = FacebookGroup::where('facebook_group_id', $groupId)->first();
        if ($group === null) {
            $this->error("Group {$groupId} chưa có trong danh sách. Thêm group ở /groups trước.");

            return self::FAILURE;
        }
        if (! $group->is_active) {
            $this->warn('Group đang tắt; vẫn crawl vì đây là lệnh chạy thủ công.');
        }

        $this->info("Đang crawl {$limits->maxPosts} bài mới nhất của group {$group->facebook_group_id} ".($group->name ? "({$group->name})" : '').'...');

        try {
            $run = $service->crawl($group, $group->crawlRuns()->create(['max_posts' => $limits->maxPosts]));
        } catch (Throwable $e) {
            $this->error('Lỗi không mong đợi: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Crawl run', 'Status', 'Posts found', 'Posts created', 'Thời gian (s)'],
            [[$run->id, $run->status->value, $run->posts_found, $run->posts_created, $run->started_at?->diffInSeconds($run->finished_at, true) ?? 0]],
        );

        if ($run->status !== CrawlRunStatus::Success) {
            $this->error((string) $run->error_message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

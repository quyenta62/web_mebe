@foreach ($crawlFinished as $run)
    @php $groupName = $run->group?->name ?? $run->group?->facebook_group_id; @endphp
    @if ($run->status === \App\Enums\CrawlRunStatus::Success)
        <div class="alert alert-success" role="status">
            <strong>Crawl xong</strong> group {{ $groupName }}: lấy được {{ $run->posts_found }}{{ $run->max_posts ? '/'.$run->max_posts : '' }} bài,
            <strong>{{ $run->posts_created }} bài mới</strong>.
            @if ($run->max_posts && $run->posts_found < $run->max_posts)
                <span class="text-secondary">(Ít hơn số yêu cầu: Facebook không tải thêm được bài.)</span>
            @endif
            <a href="{{ route('posts.index', ['group' => $run->facebook_group_id]) }}" class="alert-link ms-1">Xem bài</a>
        </div>
    @else
        <div class="alert alert-danger" role="alert">
            <strong>Crawl thất bại</strong> group {{ $groupName }}: {{ $run->error_message }}
            @if ($run->posts_created > 0)
                <span class="text-secondary">(Đã lưu {{ $run->posts_created }} bài mới đọc được trước khi lỗi.)</span>
            @endif
        </div>
    @endif
@endforeach

@foreach ($crawlRunning as $run)
    <div class="alert alert-info" role="status">
        @if ($run->status === \App\Enums\CrawlRunStatus::Running)
            <strong>Đang crawl</strong> {{ $run->max_posts ?? '' }} bài mới nhất của group {{ $run->group?->name ?? $run->group?->facebook_group_id }}
            (bắt đầu {{ \App\Support\DisplayTime::format($run->started_at, 'H:i:s') }})…
        @else
            <strong>Đang chờ</strong> crawl group {{ $run->group?->name ?? $run->group?->facebook_group_id }}…
            @if ($run->created_at->lt(now()->subMinutes(2)))
                <span class="text-secondary">Chờ lâu bất thường: kiểm tra queue worker (<code>docker compose ps</code>).</span>
            @endif
        @endif
        @if (request()->routeIs('groups.index'))
            <span class="text-secondary">Trang sẽ tự cập nhật.</span>
        @else
            <span class="text-secondary">Tải lại trang để xem kết quả.</span>
        @endif
    </div>
@endforeach

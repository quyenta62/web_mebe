@extends('layouts.app')

@section('title', 'Groups')

@push('styles')
<style>
    .crawl-menu > summary { list-style: none; }
    .crawl-menu > summary::-webkit-details-marker { display: none; }
    .crawl-menu .card { width: 230px; }
    .crawl-menu[open] { position: relative; }
    /* Header menu floats over the page instead of pushing the title down. */
    .crawl-menu-floating { position: absolute; right: 0; z-index: 10; }
    /* On phones the header buttons wrap under the title: open the menu in place so it stays on screen. */
    @media (max-width: 767.98px) {
        .crawl-menu-floating { position: static; }
        /* An open menu takes the whole row; the other buttons wrap below it. */
        .crawl-menu[open] { flex-basis: 100%; }
    }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Facebook Groups</h1>
    <div class="d-flex flex-wrap gap-2 align-items-start">
        @if ($activeCount > 0)
            @include('groups._crawl-picker', ['action' => route('groups.crawl-all'), 'label' => "Lấy dữ liệu mới", 'floating' => true])
        @else
            <button type="button" class="btn btn-sm btn-primary" disabled title="Không có group active">Crawl all</button>
        @endif
        <a href="{{ route('groups.create') }}" class="btn btn-sm btn-outline-primary">Thêm group</a>
    </div>
</div>

@if ($groups->isEmpty())
    <div class="alert alert-info">Chưa có group nào. Bấm <strong>Thêm group</strong> để bắt đầu.</div>
@else
    <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white align-middle table-stack">
            <thead class="table-light">
            <tr>
                <th>Facebook Group ID</th>
                <th>Tên</th>
                <th>URL</th>
                <th>Trạng thái</th>
                <th class="text-end">Posts</th>
                <th>Crawl gần nhất</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($groups as $group)
                <tr>
                    <td data-label="Group ID"><code>{{ $group->facebook_group_id }}</code></td>
                    <td data-label="Tên">{{ $group->name ?? '—' }}</td>
                    <td data-label="URL" class="text-break">
                        <a href="{{ $group->url }}" target="_blank" rel="noopener noreferrer">{{ $group->url }}</a>
                    </td>
                    <td data-label="Trạng thái">
                        @if ($group->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Disabled</span>
                        @endif
                    </td>
                    <td data-label="Posts" class="text-end">{{ number_format($group->posts_count) }}</td>
                    <td data-label="Crawl gần nhất" class="text-nowrap">
                        <div>
                            {{ \App\Support\DisplayTime::format($group->last_crawled_at) }}
                            @php $run = $group->latestCrawlRun; @endphp
                            @if ($run?->status === \App\Enums\CrawlRunStatus::Pending)
                                <div><span class="badge text-bg-info">Đang chờ</span></div>
                            @elseif ($run?->status === \App\Enums\CrawlRunStatus::Running)
                                <div><span class="badge text-bg-primary">Đang crawl</span></div>
                            @elseif ($run?->status === \App\Enums\CrawlRunStatus::Failed)
                                <div><a href="{{ route('crawl-runs.show', $run) }}" class="badge text-bg-danger text-decoration-none" title="{{ $run->error_message }}">Lỗi lần gần nhất</a></div>
                            @endif
                        </div>
                    </td>
                    <td class="text-end text-nowrap stack-actions">
                        @if (in_array($group->latestCrawlRun?->status, [\App\Enums\CrawlRunStatus::Pending, \App\Enums\CrawlRunStatus::Running], true))
                            <button type="button" class="btn btn-sm btn-primary" disabled>Crawl</button>
                        @else
                            @include('groups._crawl-picker', ['action' => route('groups.crawl', $group), 'label' => 'Crawl'])
                        @endif
                        <a href="{{ route('groups.edit', $group) }}" class="btn btn-sm btn-outline-primary">Sửa</a>
                        <form method="POST" action="{{ route('groups.toggle', $group) }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $group->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                {{ $group->is_active ? 'Tắt' : 'Bật' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('groups.destroy', $group) }}" class="d-inline"
                              onsubmit="return confirm('Xoá group này? Posts và lịch sử crawl vẫn được giữ lại.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <span class="text-secondary small">
            Hiển thị {{ $groups->firstItem() }}–{{ $groups->lastItem() }} / {{ number_format($groups->total()) }} groups
        </span>
        {{ $groups->links('pagination::bootstrap-5') }}
    </div>
@endif
@endsection

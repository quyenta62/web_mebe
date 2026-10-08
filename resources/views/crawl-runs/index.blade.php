@extends('layouts.app')

@section('title', 'Crawl Runs')

@section('content')
<h1 class="h4 mb-3">Crawl Runs</h1>

@if ($runs->isEmpty())
    <div class="alert alert-info">Chưa có lần crawl nào.</div>
@else
    <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white align-middle table-stack">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Group</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Status</th>
                <th class="text-end">Posts found</th>
                <th class="text-end">Posts created</th>
                <th>Error</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($runs as $run)
                <tr>
                    <td data-label="Crawl run"><a href="{{ route('crawl-runs.show', $run) }}">#{{ $run->id }}</a></td>
                    <td data-label="Group">
                        <span>
                            {{ $run->group?->name ?? $run->group?->facebook_group_id ?? '—' }}
                            @if ($run->group?->trashed())<span class="badge text-bg-secondary">đã xoá</span>@endif
                        </span>
                    </td>
                    <td data-label="Started" class="text-nowrap">{{ \App\Support\DisplayTime::format($run->started_at) }}</td>
                    <td data-label="Finished" class="text-nowrap">{{ \App\Support\DisplayTime::format($run->finished_at) }}</td>
                    <td data-label="Status">@include('crawl-runs._status', ['run' => $run])</td>
                    <td data-label="Posts found" class="text-end">
                        <span>{{ number_format($run->posts_found) }}@if ($run->max_posts)<span class="text-secondary">/{{ $run->max_posts }}</span>@endif</span>
                    </td>
                    <td data-label="Posts created" class="text-end">{{ number_format($run->posts_created) }}</td>
                    <td data-label="Error" class="text-break" style="max-width: 360px">
                        @if ($run->error_message)
                            <a href="{{ route('crawl-runs.show', $run) }}" class="text-danger text-decoration-none" title="Xem chi tiết lỗi">
                                {{ \Illuminate\Support\Str::limit($run->error_message, 120) }}
                            </a>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-secondary small">
            Hiển thị {{ $runs->firstItem() }}–{{ $runs->lastItem() }} / {{ number_format($runs->total()) }} crawl runs
        </span>
        {{ $runs->links('pagination::bootstrap-5') }}
    </div>
@endif
@endsection

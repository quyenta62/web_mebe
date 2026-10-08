@extends('layouts.app')

@section('title', 'Crawl run #'.$run->id)

@section('content')
<div class="mb-3">
    <a href="{{ route('crawl-runs.index') }}" class="small">&larr; Crawl Runs</a>
</div>
<h1 class="h4 mb-3">Crawl run #{{ $run->id }} @include('crawl-runs._status', ['run' => $run])</h1>

<div class="card mb-3" style="max-width: 820px">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Group</dt>
            <dd class="col-sm-8">
                {{ $run->group?->name ?? '—' }}
                <code>{{ $run->group?->facebook_group_id }}</code>
                @if ($run->group?->trashed())
                    <span class="badge text-bg-secondary">đã xoá</span>
                @elseif ($run->group)
                    · <a href="{{ route('posts.index', ['group' => $run->group->id]) }}">Xem bài của group</a>
                @endif
            </dd>

            <dt class="col-sm-4">Số bài yêu cầu</dt>
            <dd class="col-sm-8">{{ $run->max_posts ?? 'mặc định ('.config('crawler.max_posts').')' }}</dd>

            <dt class="col-sm-4">Posts found</dt>
            <dd class="col-sm-8">{{ number_format($run->posts_found) }}</dd>

            <dt class="col-sm-4">Posts created</dt>
            <dd class="col-sm-8">{{ number_format($run->posts_created) }}</dd>

            <dt class="col-sm-4">Tạo lúc</dt>
            <dd class="col-sm-8">{{ \App\Support\DisplayTime::format($run->created_at) }}</dd>

            <dt class="col-sm-4">Started</dt>
            <dd class="col-sm-8">{{ \App\Support\DisplayTime::format($run->started_at) }}</dd>

            <dt class="col-sm-4">Finished</dt>
            <dd class="col-sm-8">{{ \App\Support\DisplayTime::format($run->finished_at) }}</dd>

            <dt class="col-sm-4">Thời gian chạy</dt>
            <dd class="col-sm-8">{{ $run->durationSeconds() === null ? '—' : $run->durationSeconds().' giây' }}</dd>
        </dl>
    </div>
</div>

@if ($run->error_message)
    <div class="card border-danger" style="max-width: 820px">
        <div class="card-header bg-danger-subtle text-danger-emphasis">
            Lỗi @if ($run->errorCode())<code>{{ $run->errorCode() }}</code>@endif
        </div>
        <div class="card-body">
            <pre class="mb-0" style="white-space: pre-wrap">{{ $run->error_message }}</pre>
        </div>
    </div>
@endif
@endsection

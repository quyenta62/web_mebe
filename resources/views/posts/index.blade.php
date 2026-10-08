@extends('layouts.app')

@section('title', 'Posts')

@push('styles')
<style>
    .feed { max-width: 680px; }
    .post-avatar { width: 40px; height: 40px; flex: 0 0 40px; }
    .post-content { white-space: pre-line; overflow-wrap: anywhere; }
    .post-images { display: grid; gap: 2px; grid-template-columns: 1fr 1fr; background: #e9ecef; }
    .post-images.count-1 { grid-template-columns: 1fr; }
    .post-image { position: relative; display: block; aspect-ratio: 1 / 1; overflow: hidden; }
    .post-images.count-1 .post-image { aspect-ratio: auto; max-height: 600px; }
    .post-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .post-images.count-1 .post-image img { height: auto; max-height: 600px; object-fit: contain; background: #f0f2f5; }
    .post-image .more { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        background: rgba(0, 0, 0, .45); color: #fff; font-size: 2rem; font-weight: 600; }
</style>
@endpush

@section('content')
<h1 class="h4 mb-3">Posts</h1>

<form method="GET" action="{{ route('posts.index') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="keyword" class="form-label">Keyword</label>
            <input id="keyword" name="keyword" type="text" maxlength="500" value="{{ is_string($filters['keyword'] ?? null) ? $filters['keyword'] : '' }}"
                   placeholder="pass, bán, xe đẩy" class="form-control @if ($filterErrors->has('keyword')) is-invalid @endif">
            <div class="form-text">Phân cách bằng dấu phẩy; khớp <strong>bất kỳ</strong> keyword nào. Không phân biệt hoa thường, có phân biệt dấu.</div>
        </div>
        <div class="col-md-3">
            <label for="group" class="form-label">Group</label>
            <select id="group" name="group" class="form-select @if ($filterErrors->has('group')) is-invalid @endif">
                <option value="">All Groups</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected((string) ($filters['group'] ?? '') === (string) $group->id)>
                        {{ $group->name ?? $group->facebook_group_id }}{{ $group->trashed() ? ' (đã xoá)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="date_from" class="form-label">Từ ngày</label>
            <input id="date_from" name="date_from" type="date" value="{{ is_string($filters['date_from'] ?? null) ? $filters['date_from'] : '' }}"
                   class="form-control @if ($filterErrors->has('date_from')) is-invalid @endif">
        </div>
        <div class="col-6 col-md-2">
            <label for="date_to" class="form-label">Đến ngày</label>
            <input id="date_to" name="date_to" type="date" value="{{ is_string($filters['date_to'] ?? null) ? $filters['date_to'] : '' }}"
                   class="form-control @if ($filterErrors->has('date_to')) is-invalid @endif">
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </div>
    <div class="mt-2">
        <a href="{{ route('posts.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        @if ($keywords)
            <span class="ms-2 small text-secondary">Đang tìm:
                @foreach ($keywords as $keyword)
                    <span class="badge text-bg-light border">{{ $keyword }}</span>@if (! $loop->last) <span class="text-secondary">OR</span> @endif
                @endforeach
            </span>
        @endif
    </div>
    @if ($filterErrors->any())
        <div class="alert alert-warning mt-2 mb-0 py-2">
            @foreach ($filterErrors->all() as $message)
                <div>{{ $message }} Bộ lọc này đang bị bỏ qua.</div>
            @endforeach
        </div>
    @endif
</form>

<div class="feed mx-auto">
    <div class="text-secondary small mb-2">
        @if ($posts->total() > 0)
            Hiển thị {{ $posts->firstItem() }}–{{ $posts->lastItem() }} / {{ number_format($posts->total()) }} posts
        @else
            0 posts
        @endif
    </div>

    @forelse ($posts as $post)
        @include('posts._card', ['post' => $post])
    @empty
        <div class="alert alert-info">Không có bài viết nào phù hợp.</div>
    @endforelse

    <div class="d-flex justify-content-center mt-3">
        {{ $posts->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

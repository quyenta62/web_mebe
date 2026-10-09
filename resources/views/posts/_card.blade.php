@php
    $group = $post->group;
    $groupName = $group?->name ?? $group?->facebook_group_id ?? '—';
    $images = $post->image_urls ?? [];
    $shown = array_slice($images, 0, 4);
    $hidden = count($images) - count($shown);
    // The server records the check when the link opens; this only shows the label right away in this tab.
    $markChecked = "this.closest('article').querySelector('.checked-badge').classList.remove('d-none')";
@endphp
<article class="card mb-3 shadow-sm">
    <div class="card-body pb-2">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="post-avatar rounded-circle bg-primary-subtle text-primary fw-semibold d-flex align-items-center justify-content-center">
                {{ mb_strtoupper(mb_substr($groupName, 0, 1)) }}
            </div>
            <div class="lh-sm">
                <div class="fw-semibold">
                    {{ $groupName }}
                    @if ($group?->trashed())<span class="badge text-bg-secondary">đã xoá</span>@endif
                </div>
                <div class="small text-secondary">
                    {{ $post->author_name ?? 'Không rõ tác giả' }} ·
                    @if ($post->post_url)
                        <a href="{{ route('posts.open', $post) }}" target="_blank" rel="noopener noreferrer" class="text-secondary" onclick="{{ $markChecked }}">{{ \App\Support\DisplayTime::format($post->posted_at) }}</a>
                    @else
                        {{ \App\Support\DisplayTime::format($post->posted_at) }}
                    @endif
                    <span class="checked-badge badge text-bg-success ms-1 @if (! $post->checked_at) d-none @endif"
                          title="{{ $post->checked_at ? 'Mở lần đầu lúc '.\App\Support\DisplayTime::format($post->checked_at) : 'Vừa mở' }}">✓ Đã check</span>
                </div>
            </div>
        </div>

        @if ($post->content === null)
            @if ($images === [])<p class="text-secondary mb-1">(không có nội dung chữ)</p>@endif
        @elseif (mb_strlen($post->content) <= 600)
            <p class="post-content mb-1">{{ $post->content }}</p>
        @else
            <details class="mb-1">
                <summary class="post-content list-unstyled">{{ mb_substr($post->content, 0, 600) }}… <span class="text-primary">Xem thêm</span></summary>
                <p class="post-content mb-0">{{ mb_substr($post->content, 600) }}</p>
            </details>
        @endif
    </div>

    @if ($shown !== [])
        {{-- Facebook image URLs expire; a broken image is hidden instead of showing an icon. --}}
        <div class="post-images count-{{ count($shown) }}">
            @foreach ($shown as $index => $url)
                <a href="{{ $post->post_url ? route('posts.open', $post) : $url }}" target="_blank" rel="noopener noreferrer" class="post-image"
                   @if ($post->post_url) onclick="{{ $markChecked }}" @endif>
                    <img src="{{ $url }}" alt="Ảnh {{ $index + 1 }} của bài viết" loading="lazy" decoding="async"
                         referrerpolicy="no-referrer" onerror="this.parentElement.style.display='none'">
                    @if ($loop->last && $hidden > 0)<span class="more">+{{ $hidden }}</span>@endif
                </a>
            @endforeach
        </div>
    @endif

    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center small">
        <span class="text-secondary">{{ count($images) > 0 ? count($images).' ảnh' : '' }}</span>
        @if ($post->post_url)
            <a href="{{ route('posts.open', $post) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" onclick="{{ $markChecked }}">Open Facebook</a>
        @endif
    </div>
</article>

{{-- Click-to-open post count picker (<details>, no JavaScript). Params: $action, $label, $floating (optional). --}}
<details class="crawl-menu d-inline-block text-start align-top">
    <summary class="btn btn-sm btn-primary">{{ $label }} ▾</summary>
    <div class="card card-body p-2 mt-1 shadow-sm {{ ($floating ?? false) ? 'crawl-menu-floating' : '' }}">
        <div class="small text-secondary mb-1">Số bài mới nhất cần lấy</div>
        <form method="POST" action="{{ $action }}" class="d-flex gap-1 mb-2">
            @csrf
            @foreach ([20, 50, 100, 200] as $count)
                <button type="submit" name="max_posts" value="{{ $count }}" class="btn btn-sm btn-outline-primary">{{ $count }}</button>
            @endforeach
        </form>
        <form method="POST" action="{{ $action }}" class="input-group input-group-sm">
            @csrf
            <input type="number" name="max_posts" min="1" max="200" required class="form-control" placeholder="Số khác" aria-label="Số bài">
            <button type="submit" class="btn btn-primary">Crawl</button>
        </form>
    </div>
</details>

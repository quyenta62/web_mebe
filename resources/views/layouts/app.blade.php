<!doctype html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Night mode: Bootstrap dark theme; native inputs, date pickers and scrollbars follow. --}}
    <meta name="color-scheme" content="dark">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @if ($crawlRunning->isNotEmpty() && request()->routeIs('groups.index', 'posts.index'))
        {{-- Refresh until the crawls started in this browser finish (no JavaScript needed). --}}
        <meta http-equiv="refresh" content="10">
    @endif
    <style>
        /* Night mode layering: page darkest, cards and navbar one step lighter. */
        :root { color-scheme: dark; }
        .card { --bs-card-bg: var(--bs-tertiary-bg); }

        /* Night mode contrast: Bootstrap's grey text (#6c757d) and borders are too dim on dark
           backgrounds; lift muted text to >= 4.5:1 and borders to >= 3:1. */
        [data-bs-theme=dark] {
            --bs-secondary-color: #c3cad1;
            --bs-border-color: #868e96;
            --bs-border-color-translucent: rgba(255, 255, 255, .35);
        }
        [data-bs-theme=dark] .text-secondary { color: var(--bs-secondary-color) !important; }
        [data-bs-theme=dark] .navbar { --bs-navbar-color: rgba(255, 255, 255, .85); --bs-navbar-hover-color: #fff; }
        [data-bs-theme=dark] .btn-outline-secondary {
            --bs-btn-color: #dee2e6; --bs-btn-border-color: #adb5bd;
            --bs-btn-hover-color: #fff; --bs-btn-hover-bg: #6c757d; --bs-btn-hover-border-color: #adb5bd;
            --bs-btn-active-color: #fff; --bs-btn-active-bg: #6c757d; --bs-btn-active-border-color: #adb5bd;
        }

        /* Always-visible "back to top" button (bottom right); main gets room so it never hides content. */
        @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
        .scroll-top { position: fixed; right: 1rem; bottom: 1rem; z-index: 1030; width: 3rem; height: 3rem; padding: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem; line-height: 1; }
        main { padding-bottom: 5rem !important; }

        /* Phones: tables marked .table-stack show one card per row, labelled from data-label. */
        @media (max-width: 767.98px) {
            .navbar .nav-link { padding-left: .5rem; padding-right: .5rem; }
            /* Not enough room on one line: the last-crawl time gets its own line under the menu
               (navbar-expand sets nowrap, so wrapping is re-allowed here). */
            .navbar > .container { flex-wrap: wrap; }
            .navbar .nav-link { white-space: nowrap; }
            .navbar .latest-crawl { flex-basis: 100%; padding-top: 0; }
            .table-responsive:has(> .table-stack) { overflow: visible; }
            .table-stack { border: 0; background: transparent !important; --bs-table-bg: transparent; }
            .table-stack thead { display: none; }
            .table-stack, .table-stack tbody, .table-stack tr, .table-stack td { display: block; width: 100%; }
            .table-stack tr { background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: .5rem .75rem; margin-bottom: .75rem; }
            .table-stack td { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding: .3rem 0 !important;
                border: 0 !important; box-shadow: none !important; text-align: right !important; white-space: normal !important;
                max-width: none !important; overflow-wrap: anywhere; }
            .table-stack td::before { content: attr(data-label); flex: 0 0 auto; font-weight: 600; color: var(--bs-secondary-color); text-align: left; }
            .table-stack td.stack-actions { flex-wrap: wrap; justify-content: flex-start; gap: .4rem; padding-top: .6rem !important; }
            .table-stack td.stack-actions::before { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand bg-body-tertiary border-bottom mb-4">
        <div class="container">
            <span class="navbar-brand">
                <span class="d-none d-sm-inline">{{ config('app.name') }}</span>
                <span class="d-sm-none">FB Monitor</span>
            </span>
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('groups.*')) active fw-semibold @endif" href="{{ route('groups.index') }}">Groups</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('posts.*')) active fw-semibold @endif" href="{{ route('posts.index') }}">Posts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('crawl-runs.*')) active fw-semibold @endif" href="{{ route('crawl-runs.index') }}">Crawl Runs</a>
                </li>
            </ul>
            <a href="{{ route('crawl-runs.index') }}" class="latest-crawl navbar-text small text-secondary text-decoration-none" title="Lần crawl thành công gần nhất">
                @if ($latestCrawlAt)
                    Data mới nhất: <span class="text-body">{{ \App\Support\DisplayTime::format($latestCrawlAt) }}</span>
                @else
                    Chưa crawl lần nào
                @endif
            </a>
        </div>
    </nav>

<main class="container pb-5">
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach
        </div>
    @endif
    @include('partials.crawl-notifications')

    @yield('content')
</main>

{{-- No JavaScript needed: the #top fragment scrolls to the top of the page. --}}
<a href="#top" class="scroll-top btn btn-primary rounded-circle shadow" title="Lên đầu trang" aria-label="Lên đầu trang">↑</a>
</body>
</html>

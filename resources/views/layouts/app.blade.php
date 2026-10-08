<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @if ($crawlRunning->isNotEmpty() && request()->routeIs('groups.index'))
        {{-- Refresh until the crawls started from this page finish (no JavaScript needed). --}}
        <meta http-equiv="refresh" content="10">
    @endif
    <style>
        /* Phones: tables marked .table-stack show one card per row, labelled from data-label. */
        @media (max-width: 767.98px) {
            .navbar .nav-link { padding-left: .5rem; padding-right: .5rem; }
            .table-responsive:has(> .table-stack) { overflow: visible; }
            .table-stack { border: 0; background: transparent !important; }
            .table-stack thead { display: none; }
            .table-stack, .table-stack tbody, .table-stack tr, .table-stack td { display: block; width: 100%; }
            .table-stack tr { background: #fff; border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: .5rem .75rem; margin-bottom: .75rem; }
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
<body class="bg-light">
    <nav class="navbar navbar-expand bg-white border-bottom mb-4">
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
</body>
</html>

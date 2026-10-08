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
    @stack('styles')
</head>
<body class="bg-light">
@if (session()->has('admin_username'))
    <nav class="navbar navbar-expand bg-white border-bottom mb-4">
        <div class="container">
            <span class="navbar-brand">{{ config('app.name') }}</span>
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('groups.*')) active fw-semibold @endif" href="{{ route('groups.index') }}">Groups</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('posts.*')) active fw-semibold @endif" href="{{ route('posts.index') }}">Posts</a>
                </li>
                {{-- Page from a later phase (2.7 Crawl Runs). --}}
                <li class="nav-item"><span class="nav-link disabled" title="Phase 2.7">Crawl Runs</span></li>
            </ul>
            <form method="POST" action="{{ route('logout') }}" class="d-flex align-items-center gap-2">
                @csrf
                <span class="text-secondary small">{{ session('admin_username') }}</span>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Đăng xuất</button>
            </form>
        </div>
    </nav>
@endif

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

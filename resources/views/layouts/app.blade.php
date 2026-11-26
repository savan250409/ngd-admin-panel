@php
    use App\Support\ModuleRegistry;
    $modulesNav = ModuleRegistry::all();
    $current = request()->route('module') ?? collect($modulesNav)->keys()->first(fn ($k) => request()->is($k . '/*'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}?v=10" rel="stylesheet">
    @stack('styles')
</head>
<body>
<div class="shell">
    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark"><i class="bi bi-hexagon-half"></i></span>
            <span class="brand-text">NGD<small>Admin Panel</small></span>
        </a>

        <nav class="side-nav">
            <div class="nav-label">Overview</div>
            <a href="{{ route('dashboard') }}" class="nav-link-x {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <a href="{{ route('apps.index') }}" class="nav-link-x {{ request()->routeIs('apps.*') ? 'active' : '' }}">
                <i class="bi bi-phone"></i><span>Apps</span>
            </a>

            <div class="nav-label">Modules</div>
            @foreach ($modulesNav as $key => $m)
                @php $open = $current === $key; @endphp
                <div class="nav-group {{ $open ? 'open' : '' }}">
                    <button type="button" class="nav-link-x nav-toggle" data-bs-toggle="collapse" data-bs-target="#nav-{{ $key }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                        <i class="bi {{ $m['icon'] }}"></i><span>{{ $m['title'] }}</span><i class="bi bi-chevron-down caret"></i>
                    </button>
                    <div class="collapse {{ $open ? 'show' : '' }}" id="nav-{{ $key }}">
                        <a href="{{ route('categories.index', $key) }}" class="nav-sub {{ request()->routeIs('categories.*') && $open ? 'active' : '' }}">Categories</a>
                        <a href="{{ route($key . '.items.index') }}" class="nav-sub {{ request()->routeIs($key . '.items.*') ? 'active' : '' }}">{{ $m['item_title'] }}</a>
                    </div>
                </div>
            @endforeach

            <div class="nav-label">Developer</div>
            <a href="{{ route('api-docs.index') }}" class="nav-link-x {{ request()->routeIs('api-docs.*') ? 'active' : '' }}">
                <i class="bi bi-braces-asterisk"></i><span>API List</span>
            </a>
        </nav>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    {{-- ── Main ────────────────────────────────────────────────── --}}
    <div class="main">
        <header class="topbar">
            <button class="icon-btn d-lg-none" type="button" data-sidebar-open aria-label="Open menu"><i class="bi bi-list"></i></button>
            <div class="topbar-title">
                <div class="crumbs">@yield('crumbs', 'Overview')</div>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="user-chip" data-bs-toggle="dropdown" type="button">
                        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-2 small text-muted">{{ auth()->user()->email }}</li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>
</div>

<div id="flash" data-success="{{ session('success') }}" data-error="{{ session('error') }}" hidden></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/admin.js') }}?v=4"></script>
@if (config('admin.media_security'))
    <script src="{{ asset('js/media-protect.js') }}?v=3"></script>
@endif
@stack('scripts')
</body>
</html>

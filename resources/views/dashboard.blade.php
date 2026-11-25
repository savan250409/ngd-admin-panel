@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumbs', 'Overview')

@section('content')
    @php
        $totalCats = collect($modules)->sum('categories');
        $totalItems = collect($modules)->sum('items');
    @endphp

    <div class="hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h2>Hello, {{ auth()->user()->name }} 👋</h2>
                <p>Here is what is live across your apps today.</p>
            </div>
            <div class="col-lg-6">
                <div class="d-flex justify-content-lg-end gap-5">
                    <div><div class="big">{{ $appCount }}</div><div class="lbl">Apps</div></div>
                    <div><div class="big">{{ $totalCats }}</div><div class="lbl">Categories</div></div>
                    <div><div class="big">{{ $totalItems }}</div><div class="lbl">Content items</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach (array_values($modules) as $i => $m)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route($m['key'] . '.items.index') }}" class="panel mod-card">
                    <div class="d-flex align-items-center gap-3">
                        <span class="mod-icon c{{ $i + 1 }}"><i class="bi {{ $m['icon'] }}"></i></span>
                        <div>
                            <div class="fw-bold">{{ $m['title'] }}</div>
                            <small class="text-muted">{{ $m['item_title'] }}</small>
                        </div>
                    </div>
                    <div class="mod-stats">
                        <div><b>{{ $m['categories'] }}</b><span>Categories</span></div>
                        <div><b>{{ $m['items'] }}</b><span>{{ !empty($m['gallery']) ? 'Images' : 'Items' }}</span></div>
                        <div><b>{{ $m['active'] }}</b><span>Active</span></div>
                    </div>
                </a>
            </div>
        @endforeach
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('api-docs.index') }}" class="panel mod-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="mod-icon" style="background:#0b1120"><i class="bi bi-braces-asterisk"></i></span>
                    <div>
                        <div class="fw-bold">API List</div>
                        <small class="text-muted">{{ count($modules) * 2 }} endpoints</small>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">Bearer-token protected endpoints, sample responses and a Postman collection.</p>
            </a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Recent apps</h2>
                <div class="sub">{{ $activeApps }} of {{ $appCount }} active</div>
            </div>
            <a href="{{ route('apps.create') }}" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-plus-lg me-1"></i>Add app</a>
        </div>
        <div class="table-responsive">
            <table class="table table-x">
                <thead><tr><th>App</th><th>Package name</th><th>Status</th><th>Added</th></tr></thead>
                <tbody>
                @forelse ($recentApps as $app)
                    <tr>
                        <td class="fw-semibold">{{ $app->app_name }}</td>
                        <td class="mono">{{ $app->package_name }}</td>
                        <td><span class="chip {{ $app->status ? 'chip-ok' : '' }}">{{ $app->status ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-muted">{{ $app->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty-state"><i class="bi bi-phone"></i>No apps yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

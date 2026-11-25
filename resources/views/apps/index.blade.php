@extends('layouts.app')
@section('title', 'Apps')
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / Apps @endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>All apps</h2>
                <div class="sub">Each app is identified by its package name. A category can be shared across several apps.</div>
            </div>
            <a href="{{ route('apps.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Add app</a>
        </div>

        @include('partials.toolbar', ['action' => route('apps.index'), 'placeholder' => 'Search app or package name…'])

        <div id="list-area">
            <div class="table-responsive">
                <table class="table table-x">
                    <thead>
                    <tr><th>#</th><th>App name</th><th>Package name</th><th>Categories</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($apps as $app)
                        @php $counts = $categoryCounts[$app->id] ?? []; @endphp
                        <tr>
                            <td class="id-badge">{{ $app->id }}</td>
                            <td class="fw-semibold">{{ $app->app_name }}</td>
                            <td>
                                <span class="mono">{{ $app->package_name }}</span>
                                <button class="btn btn-sm btn-link p-0 ms-1 text-muted" data-copy="{{ $app->package_name }}" title="Copy"><i class="bi bi-copy"></i></button>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse ($counts as $c)
                                        <span class="chip chip-brand">{{ $c['title'] }} · {{ $c['count'] }}</span>
                                    @empty
                                        <span class="text-muted small">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>@include('partials.switch', ['url' => route('apps.toggle', $app->id), 'value' => $app->status])</td>
                            <td>@include('partials.actions', [
                                'edit' => route('apps.edit', $app->id),
                                'delete' => route('apps.destroy', $app->id),
                                'confirm' => 'Delete the app "' . $app->app_name . '"?',
                            ])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-phone"></i>No apps found.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pager', ['p' => $apps])
        </div>
    </div>
@endsection

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
                                @php $mods = $appModules[$app->id] ?? []; @endphp
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    @forelse ($counts as $c)
                                        <span class="chip chip-brand">{{ $c['title'] }} · {{ $c['count'] }}</span>
                                    @empty
                                        <span class="text-muted small">—</span>
                                    @endforelse
                                    @if (!empty($mods))
                                        <button type="button" class="btn-view-cats" data-app="{{ $app->app_name }}" data-modules="{{ json_encode($mods) }}" title="View all categories">
                                            <i class="bi bi-grid-3x3-gap-fill"></i> View all
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td>@include('partials.switch', ['url' => route('apps.toggle', $app->id), 'value' => $app->status])</td>
                            @php $catTotal = array_sum(array_column($counts, 'count')); @endphp
                            <td>@include('partials.actions', [
                                'edit' => route('apps.edit', $app->id),
                                'delete' => route('apps.destroy', $app->id),
                                'confirm' => 'Deleting <b>' . e($app->app_name) . '</b> removes it from all its categories' . ($catTotal ? ', and <b>deletes the categories used only by it</b> (with their items and files)' : '') . '.',
                                'confirm2' => 'Permanently delete the app <b>' . e($app->app_name) . '</b>' . ($catTotal ? ' and the data that belongs only to it' : '') . '? This cannot be undone.',
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

    {{-- ── All categories popup (per app, grouped by module) ──────────── --}}
    <div class="modal fade" id="appCatsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-grid-3x3-gap-fill me-2"></i><span id="acm-title">Categories</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Every module this app is assigned to, with the categories served to it.</p>
                    <div id="acm-body"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('appCatsModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const titleEl = document.getElementById('acm-title');
    const bodyEl = document.getElementById('acm-body');
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-view-cats');
        if (!btn) return;
        let mods = [];
        try { mods = JSON.parse(btn.dataset.modules || '[]'); } catch (_) {}
        titleEl.textContent = `${btn.dataset.app} · all categories`;

        bodyEl.innerHTML = mods.map((m) => {
            const chips = (m.categories || []).map((c) =>
                `<span class="chip ${c.status ? 'chip-ok' : 'chip-warn'}">${esc(c.name)}</span>`
            ).join('');
            return `
                <div class="acm-group">
                    <div class="acm-head"><i class="bi ${esc(m.icon)}"></i><span>${esc(m.title)}</span><span class="acm-count">${(m.categories || []).length}</span></div>
                    <div class="acm-chips">${chips}</div>
                </div>`;
        }).join('');

        modal.show();
    });
})();
</script>
@endpush

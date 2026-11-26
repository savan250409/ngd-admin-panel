@extends('layouts.app')
@section('title', $cfg['title'] . ' Categories')
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / {{ $cfg['title'] }} / Categories @endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $cfg['title'] }} categories</h2>
                <div class="sub">{{ $categories->total() }} total · a category can be served to several apps</div>
            </div>
            <div class="ms-auto d-flex gap-2">
                @if ($allOrdered->count() > 1)
                    <button type="button" class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#orderModal" title="Set the order categories appear in the API">
                        <i class="bi bi-sort-down me-1"></i>Index
                    </button>
                @endif
                <a href="{{ route($module . '.items.index') }}" class="btn btn-soft"><i class="bi {{ $cfg['icon'] }} me-1"></i>{{ $cfg['item_title'] }}</a>
                <a href="{{ route('categories.create', $module) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add category</a>
            </div>
        </div>

        @include('partials.toolbar', ['action' => route('categories.index', $module), 'placeholder' => 'Search category name or #id…', 'apps' => $apps])

        <div id="list-area">
            <div class="table-responsive">
                <table class="table table-x">
                    <thead>
                    <tr>
                        <th>#</th><th>Image</th><th>Category</th><th>Apps</th>
                        @if (!empty($cfg['category_orientation']))<th>Orientation</th>@endif
                        <th>{{ $cfg['item_title'] }}</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($categories as $c)
                        <tr>
                            <td class="id-badge">{{ $c->id }}</td>
                            <td>@include('partials.thumb', ['url' => $c->imageUrl()])</td>
                            <td class="fw-semibold">{{ $c->name }}</td>
                            <td>
                                @if ($c->apps->isEmpty())
                                    <span class="chip chip-warn" title="No app assigned — hidden from every API">Unassigned</span>
                                @else
                                    @php $catApps = $c->apps; $shownApps = $catApps->take(3); $moreApps = $catApps->count() - $shownApps->count(); @endphp
                                    <div class="app-chips">
                                        @foreach ($shownApps as $a)
                                            <span class="chip chip-brand" title="{{ $a->package_name }}">{{ $a->app_name }}</span>
                                        @endforeach
                                        @if ($moreApps > 0)
                                            <button type="button" class="chip chip-more" data-cat="{{ $c->name }}"
                                                    data-apps="{{ json_encode($catApps->map(fn ($a) => ['name' => $a->app_name, 'package' => $a->package_name])->values()) }}"
                                                    title="Show all {{ $catApps->count() }} apps">+{{ $moreApps }} more</button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            @if (!empty($cfg['category_orientation']))
                                <td><span class="chip"><i class="bi bi-{{ $c->orientation === 'landscape' ? 'aspect-ratio' : 'phone' }}"></i>{{ ucfirst($c->orientation) }}</span></td>
                            @endif
                            <td>
                                <a href="{{ route($module . '.items.index', ['category_id' => $c->id]) }}" class="chip chip-brand text-decoration-none">{{ $c->items_count }} {{ !empty($cfg['gallery']) ? 'set' : 'items' }}</a>
                            </td>
                            <td>@include('partials.switch', ['url' => route('categories.toggle', [$module, $c->id]), 'value' => $c->status])</td>
                            <td>@include('partials.actions', [
                                'edit' => route('categories.edit', [$module, $c->id]),
                                'delete' => route('categories.destroy', [$module, $c->id]),
                                'confirm' => 'Deleting <b>' . e($c->name) . '</b> will also delete <b>all its ' . strtolower($cfg['item_title']) . '</b> and their uploaded files.',
                                'confirm2' => 'Permanently delete the <b>' . e($c->name) . '</b> category' . ($c->items_count ? ' and its <b>' . $c->items_count . '</b> ' . strtolower($c->items_count === 1 ? $cfg['item_singular'] : $cfg['item_title']) : '') . '? This cannot be undone.',
                            ])</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-folder2-open"></i>No categories found.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pager', ['p' => $categories])
        </div>
    </div>

    {{-- ── Index / reorder popup ──────────────────────────────────── --}}
    <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-sort-down me-2"></i>Category order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Drag the categories into the order you want. This is the order the API returns them in.</p>
                    <ul class="order-list" id="order-list">
                        @foreach ($allOrdered as $c)
                            <li class="order-item" draggable="true" data-id="{{ $c->id }}">
                                <span class="oi-handle"><i class="bi bi-grip-vertical"></i></span>
                                @include('partials.thumb', ['url' => $c->imageUrl(), 'class' => 'thumb-sm'])
                                <span class="oi-name">{{ $c->name }}</span>
                                <span class="oi-pos"></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="order-save" data-url="{{ route('categories.reorder', $module) }}"><i class="bi bi-check2 me-1"></i>Save order</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Apps-for-a-category popup (when one category serves many apps) ── --}}
    <div class="modal fade" id="catAppsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-phone me-2"></i><span id="ca-title">Apps</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Every app this category is served to.</p>
                    <div id="ca-body" class="app-list"></div>
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
    const modalEl = document.getElementById('catAppsModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const titleEl = document.getElementById('ca-title');
    const bodyEl = document.getElementById('ca-body');
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.chip-more');
        if (!btn) return;
        let apps = [];
        try { apps = JSON.parse(btn.dataset.apps || '[]'); } catch (_) {}
        titleEl.textContent = `${btn.dataset.cat} · ${apps.length} apps`;
        bodyEl.innerHTML = apps.map((a) =>
            `<div class="app-row"><span class="ar-name">${esc(a.name)}</span><code class="ar-pkg">${esc(a.package)}</code></div>`
        ).join('');
        modal.show();
    });
})();
</script>
<script>
(function () {
    const list = document.getElementById('order-list');
    if (!list) return;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function renumber() {
        [...list.querySelectorAll('.order-item')].forEach((li, i) => { li.querySelector('.oi-pos').textContent = i + 1; });
    }

    let dragged = null;
    list.addEventListener('dragstart', (e) => { dragged = e.target.closest('.order-item'); dragged?.classList.add('dragging'); });
    list.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; renumber(); });
    list.addEventListener('dragover', (e) => {
        if (!dragged) return;
        e.preventDefault();
        const over = e.target.closest('.order-item');
        if (!over || over === dragged) return;
        const r = over.getBoundingClientRect();
        over.parentNode.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over);
    });

    document.getElementById('order-save').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const order = [...list.querySelectorAll('.order-item')].map((li) => li.dataset.id);
        btn.disabled = true;
        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ order }),
            });
            if (!res.ok) throw new Error();
            notify('success', 'Order saved');
            setTimeout(() => location.reload(), 500);
        } catch {
            notify('error', 'Could not save the order');
            btn.disabled = false;
        }
    });

    renumber();
})();
</script>
@endpush

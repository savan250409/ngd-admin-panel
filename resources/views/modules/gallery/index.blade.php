{{-- Shared list for gallery modules (Frame, Template, Baby Backdrop): one row per category. --}}
@extends('layouts.module-index')

@php $hasOrientation = !empty($cfg['category_orientation']); @endphp

@section('summary'){{ $items->total() }} categories · {{ $totalImages }} {{ $noun }} images in total @endsection

@section('thead')
    <th>#</th><th>Category</th>@if ($hasOrientation)<th>Orientation</th>@endif<th>{{ ucfirst($noun) }}s</th><th>Status</th><th class="text-end">Actions</th>
@endsection

@section('tbody')
    @forelse ($items as $item)
        @php $urls = $item->imageUrls(); $total = count($urls); @endphp
        <tr>
            <td class="id-badge">{{ $item->id }}</td>
            <td>@include('partials.category-cell')</td>
            @if ($hasOrientation)
                <td><span class="chip">{{ ucfirst($item->category?->orientation ?? 'portrait') }}</span></td>
            @endif
            <td>
                <div class="strip">
                    @foreach (array_slice($urls, 0, 3) as $url)
                        <img src="{{ $url }}" class="thumb" alt="" loading="lazy">
                    @endforeach
                    @if ($total > 3)
                        <button type="button" class="see-more-btn"
                                data-see-more data-title="{{ $item->category?->name }} — {{ $total }} {{ \Illuminate\Support\Str::plural($noun, $total) }}">
                            <i class="bi bi-grid-3x3-gap-fill"></i> View more
                        </button>
                    @endif
                    {{-- Every entry (frame + thumbnail URLs, tokenised by the middleware), read on click. --}}
                    <div class="more-frames d-none">
                        @foreach ($item->galleryPopup() as $entry)
                            <span class="mf-item" data-frame="{{ $entry['frame'] }}" data-thumb="{{ $entry['thumbnail'] }}" data-name="{{ $entry['name'] ?? '' }}"></span>
                        @endforeach
                    </div>
                </div>
                <small class="text-muted">{{ $total }} {{ \Illuminate\Support\Str::plural($noun, $total) }}</small>
            </td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->status])</td>
            <td>@include('partials.actions', [
                'edit' => route($module . '.items.edit', $item->id),
                'delete' => route($module . '.items.destroy', $item->id),
                'confirm' => 'All ' . $total . ' ' . $noun . ' images of this category will be deleted.',
            ])</td>
        </tr>
    @empty
        <tr><td colspan="{{ $hasOrientation ? 6 : 5 }}"><div class="empty-state"><i class="bi {{ $cfg['icon'] }}"></i>No {{ $noun }}s found.</div></td></tr>
    @endforelse
@endsection

@push('scripts')
<div class="modal fade" id="framesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi {{ $cfg['icon'] }} me-2"></i><span class="t"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="frames-grid"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<script>
(function () {
    const modalEl = document.getElementById('framesModal');
    const modal = new bootstrap.Modal(modalEl);
    const grid = modalEl.querySelector('.frames-grid');

    const cell = (url) => {
        const d = document.createElement('div');
        d.className = 'pc-cell';
        if (url) { const img = new Image(); img.loading = 'lazy'; img.src = url; d.appendChild(img); }
        return d;
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-see-more]');
        if (!btn) return;
        const items = [...btn.parentElement.querySelectorAll('.more-frames .mf-item')];
        grid.innerHTML = '';
        items.forEach((it, i) => {
            const frame = it.dataset.frame || '';
            const thumb = it.dataset.thumb || '';
            const name = it.dataset.name || '';
            const card = document.createElement('div');
            card.className = 'pop-card' + (frame ? '' : ' single');
            const head = document.createElement('div'); head.className = 'pc-head'; head.textContent = 'Item #' + (i + 1);
            const body = document.createElement('div'); body.className = 'pc-body';
            if (frame) {
                const s1 = document.createElement('div'); s1.className = 'pc-slot';
                s1.appendChild(cell(frame));
                const l1 = document.createElement('div'); l1.className = 'pc-label'; l1.textContent = 'Frame'; s1.appendChild(l1);
                body.appendChild(s1);
            }
            const s2 = document.createElement('div'); s2.className = 'pc-slot';
            s2.appendChild(cell(thumb));
            const l2 = document.createElement('div'); l2.className = 'pc-label'; l2.textContent = frame ? 'Thumbnail' : 'Image'; s2.appendChild(l2);
            body.appendChild(s2);
            card.appendChild(head); card.appendChild(body);
            if (name) { const n = document.createElement('div'); n.className = 'pc-name'; n.textContent = name; card.appendChild(n); }
            grid.appendChild(card);
        });
        modalEl.querySelector('.modal-title .t').textContent = btn.dataset.title;
        modal.show();
    });
})();
</script>
@endpush

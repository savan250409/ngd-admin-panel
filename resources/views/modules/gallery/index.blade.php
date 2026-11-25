{{-- Shared list for gallery modules (Frame, Baby Backdrop): one row per category. --}}
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
                    {{-- Only the first 3 are shown; the rest open in a popup. --}}
                    @foreach (array_slice($urls, 0, 3) as $url)
                        <img src="{{ $url }}" class="thumb" alt="" loading="lazy">
                    @endforeach
                    @if ($total > 3)
                        <button type="button" class="btn btn-soft btn-sm see-more-btn"
                                data-see-more data-title="{{ $item->category?->name }} — {{ $total }} {{ \Illuminate\Support\Str::plural($noun, $total) }}">
                            <i class="bi bi-grid-3x3-gap"></i> See more ({{ $total - 3 }})
                        </button>
                        {{-- Every image URL for this row (tokenised by the middleware), read on click. --}}
                        <div class="more-frames d-none">@foreach ($urls as $url)<span>{{ $url }}</span>@endforeach</div>
                    @endif
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi {{ $cfg['icon'] }} me-2"></i><span class="t"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="frames-grid"></div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const modalEl = document.getElementById('framesModal');
    const modal = new bootstrap.Modal(modalEl);
    const grid = modalEl.querySelector('.frames-grid');

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-see-more]');
        if (!btn) return;
        const urls = [...btn.parentElement.querySelectorAll('.more-frames span')].map((s) => s.textContent.trim()).filter(Boolean);
        grid.innerHTML = '';
        urls.forEach((u) => {
            const cell = document.createElement('div');
            cell.className = 'frame-cell';
            const img = new Image();
            img.loading = 'lazy';
            img.alt = '';
            img.src = u;
            cell.appendChild(img);
            grid.appendChild(cell);
        });
        modalEl.querySelector('.modal-title .t').textContent = btn.dataset.title;
        modal.show();
    });
})();
</script>
@endpush

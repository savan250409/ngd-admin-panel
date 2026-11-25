@extends('layouts.module-index', ['searchPlaceholder' => 'Search prompt, category or #id…'])

@section('thead')
    <th>#</th><th>Thumbnail</th><th>Video</th><th>Category</th><th>Prompt</th><th>Status</th><th class="text-end">Actions</th>
@endsection

@section('tbody')
    @forelse ($items as $item)
        <tr>
            <td class="id-badge">{{ $item->id }}</td>
            <td>
                @if ($item->thumbnail)
                    @include('partials.thumb', ['url' => $item->fileUrl('thumbnail'), 'class' => 'thumb-lg'])
                @else
                    {{-- No thumbnail yet: generated from the video with <canvas> once the page loads. --}}
                    <div class="thumb-empty thumb-lg" data-missing-thumb="{{ route($module . '.items.thumbnail', $item->id) }}" data-video="{{ $item->fileUrl('video') }}">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                @endif
            </td>
            <td><video src="{{ $item->fileUrl('video') }}" controls preload="none" class="rounded-3 border" style="width:150px;height:84px;background:#000;object-fit:cover"></video></td>
            <td>@include('partials.category-cell')</td>
            <td><div class="text-clamp" title="{{ $item->prompt }}">{{ $item->prompt }}</div></td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->status])</td>
            <td>@include('partials.actions', ['edit' => route($module . '.items.edit', $item->id), 'delete' => route($module . '.items.destroy', $item->id)])</td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty-state"><i class="bi {{ $cfg['icon'] }}"></i>No AI videos found.</div></td></tr>
    @endforelse
@endsection

@push('scripts')
<script src="{{ asset('js/video-thumbnail.js') }}?v=3"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function fill(cell) {
        if (cell.dataset.working) return;
        cell.dataset.working = '1';
        try {
            const dataUrl = await captureVideoFrame(cell.dataset.video, { at: 0.25 });
            const body = new FormData();
            body.append('thumbnail_data', dataUrl);
            const res = await fetch(cell.dataset.missingThumb, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body });
            if (!res.ok) throw new Error();
            const img = new Image();
            img.src = dataUrl;
            img.className = 'thumb thumb-lg';
            cell.replaceWith(img);
        } catch {
            cell.innerHTML = '<i class="bi bi-exclamation-triangle text-warning" title="Could not generate"></i>';
        }
    }

    const scan = () => document.querySelectorAll('[data-missing-thumb]').forEach(fill);
    scan();
    document.addEventListener('list:updated', scan);
})();
</script>
@endpush

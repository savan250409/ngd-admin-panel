@extends('layouts.module-index', ['searchPlaceholder' => 'Search title, zip name, category or #id…'])

@section('thead')
    <th>#</th><th>Thumbnail</th><th>Title</th><th>Category</th><th>Details</th><th>Paid</th><th>Status</th><th class="text-end">Actions</th>
@endsection

@section('tbody')
    @forelse ($items as $item)
        <tr>
            <td class="id-badge">{{ $item->id }}</td>
            <td>@include('partials.thumb', ['url' => $item->fileUrl('thumbnail'), 'class' => 'thumb-lg'])</td>
            <td>
                <div class="fw-semibold">{{ $item->title }}</div>
                <small class="mono text-muted">{{ $item->zip_original_name }}</small>
            </td>
            <td>@include('partials.category-cell')</td>
            <td>
                <div class="d-flex flex-wrap gap-1">
                    @if ($item->width && $item->height)<span class="chip">{{ $item->width }}×{{ $item->height }}</span>@endif
                    @if (!is_null($item->total_image_count))<span class="chip chip-brand">{{ $item->total_image_count }} elements · {{ $item->total_editable }} editable</span>@endif
                    @if (!$item->video_html_path)<span class="chip chip-warn">No player</span>@endif
                </div>
            </td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->is_paid, 'field' => 'is_paid', 'on' => 'Paid', 'off' => 'Free'])</td>
            <td>@include('partials.switch', ['url' => route($module . '.items.toggle', $item->id), 'value' => $item->status])</td>
            <td>
                @php
                    $preview = $item->video_html_path
                        ? '<button type="button" class="btn btn-light border btn-icon" title="Preview" data-preview-video="' . e(route('video-template.items.player', [$item->id, $item->video_html_path])) . '" data-title="' . e($item->title) . '"><i class="bi bi-play-fill"></i></button>'
                        : '';
                @endphp
                @include('partials.actions', ['edit' => route($module . '.items.edit', $item->id), 'delete' => route($module . '.items.destroy', $item->id), 'extra' => $preview])
            </td>
        </tr>
    @empty
        <tr><td colspan="8"><div class="empty-state"><i class="bi {{ $cfg['icon'] }}"></i>No video templates found.</div></td></tr>
    @endforelse
@endsection

@push('scripts')
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header"><h5 class="modal-title fw-bold" id="previewTitle">Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0 bg-dark"><iframe id="previewFrame" title="Template preview" style="width:100%;height:70vh;border:0;display:block;background:#fff"></iframe></div>
        </div>
    </div>
</div>
<script>
(function () {
    const modalEl = document.getElementById('previewModal');
    const modal = new bootstrap.Modal(modalEl);
    const frame = document.getElementById('previewFrame');
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-preview-video]');
        if (!btn) return;
        document.getElementById('previewTitle').textContent = btn.dataset.title;
        frame.src = btn.dataset.previewVideo;
        modal.show();
    });
    modalEl.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
})();
</script>
@endpush

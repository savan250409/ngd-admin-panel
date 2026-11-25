@extends('layouts.module-form')
@section('form-sub', 'One frame set per category. Each frame has a frame image and a thumbnail — add several at once.')

@section('fields')
    @include('partials.category-select', ['selected' => $item->category_id, 'disabledIds' => $usedCategoryIds])

    @php $saved = $item->pairs(); $savedThumbUrls = $item->exists ? $item->imageUrls() : []; @endphp

    {{-- ── Saved frames ─────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-2 mt-2">
        <label class="form-label m-0">Current frames <span class="text-muted fw-normal" id="saved-count"></span></label>
        <small class="text-muted"><i class="bi bi-arrows-move"></i> Drag to reorder</small>
    </div>
    <div class="gallery mb-2" id="saved" data-allow-drag>
        @foreach (old('existing', array_keys($saved)) as $idx)
            @continue(!isset($saved[(int) $idx]))
            <div class="g-item" draggable="true" data-existing>
                <input type="hidden" name="existing[]" value="{{ (int) $idx }}">
                <img src="{{ $savedThumbUrls[(int) $idx] ?? '' }}" alt="">
                <button type="button" class="g-del" title="Remove" data-remove><i class="bi bi-x-lg"></i></button>
                <span class="g-tag">Frame</span>
            </div>
        @endforeach
    </div>
    <div id="saved-empty" class="empty-state border rounded-3 mb-3 py-3" @if (count($saved)) hidden @endif><i class="bi bi-images"></i>No frames yet — add some below.</div>

    @error('new_frames')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    @error('new_frames.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    @error('new_thumbnails.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

    {{-- ── Add new frame+thumbnail pairs ───────────────────────────── --}}
    <label class="form-label mt-2">Add frames <span class="text-muted fw-normal">— each frame needs its thumbnail</span></label>
    <div class="webp-warning mb-2"><i class="bi bi-exclamation-triangle-fill"></i><span>Only <code>.webp</code> images are allowed.</span></div>

    <div id="new-rows"></div>
    <button type="button" class="btn btn-soft btn-sm mt-1" id="add-row"><i class="bi bi-plus-lg me-1"></i>Add another frame</button>

    <template id="row-tpl">
        <div class="frame-row">
            <div class="fr-field">
                <span class="fr-label">Frame <span class="req">*</span></span>
                <input type="file" name="new_frames[]" accept="image/webp" class="form-control form-control-sm" data-webp>
            </div>
            <div class="fr-field">
                <span class="fr-label">Thumbnail <span class="req">*</span></span>
                <input type="file" name="new_thumbnails[]" accept="image/webp" class="form-control form-control-sm" data-webp>
                <img class="fr-prev" alt="" hidden>
            </div>
            <button type="button" class="btn btn-light border btn-icon fr-del" title="Remove row"><i class="bi bi-x-lg"></i></button>
        </div>
    </template>

    <div class="section-divider my-3"></div>
    @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Inactive sets are hidden from the API.', 'checked' => old('status', $item->status)])
@endsection

@push('scripts')
<script>
(function () {
    const saved = document.getElementById('saved');
    const savedEmpty = document.getElementById('saved-empty');
    const savedCount = document.getElementById('saved-count');
    const rows = document.getElementById('new-rows');
    const tpl = document.getElementById('row-tpl');
    const isWebp = (f) => f && (f.type === 'image/webp' || /\.webp$/i.test(f.name || ''));

    function refreshSaved() {
        const n = saved.querySelectorAll('.g-item').length;
        savedEmpty.hidden = n > 0;
        savedCount.textContent = n ? `(${n})` : '';
    }

    // Remove a saved pair.
    saved.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove]');
        if (btn) { btn.closest('.g-item').remove(); refreshSaved(); }
    });

    // Drag reorder saved pairs.
    let dragged = null;
    saved.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('.g-item[data-existing]');
        if (!dragged) return e.preventDefault();
        dragged.classList.add('dragging');
    });
    saved.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; });
    saved.addEventListener('dragover', (e) => {
        if (!dragged) return;
        e.preventDefault();
        const over = e.target.closest('.g-item[data-existing]');
        if (!over || over === dragged) return;
        const r = over.getBoundingClientRect();
        over.parentNode.insertBefore(dragged, (e.clientX - r.left) > r.width / 2 ? over.nextSibling : over);
    });

    // Add / remove new-frame rows.
    function addRow() {
        rows.appendChild(tpl.content.cloneNode(true));
    }
    document.getElementById('add-row').addEventListener('click', addRow);

    rows.addEventListener('click', (e) => {
        const del = e.target.closest('.fr-del');
        if (del) del.closest('.frame-row').remove();
    });

    // WebP guard + thumbnail preview for dynamically added rows.
    rows.addEventListener('change', (e) => {
        const input = e.target;
        if (!input.matches('input[type=file][data-webp]')) return;
        const file = input.files[0];
        if (file && !isWebp(file)) {
            input.value = '';
            const prev = input.parentElement.querySelector('.fr-prev');
            if (prev) prev.hidden = true;
            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Only .webp images allowed', confirmButtonColor: '#4f46e5' });
            return;
        }
        const prev = input.parentElement.querySelector('.fr-prev');
        if (prev && file) { prev.src = URL.createObjectURL(file); prev.hidden = false; }
    });

    refreshSaved();
    if (!saved.querySelectorAll('.g-item').length) addRow(); // start with one empty row on create
})();
</script>
@endpush

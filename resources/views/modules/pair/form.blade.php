@extends('layouts.module-form')
@section('form-sub', 'One ' . $noun . ' set per category. Each ' . $noun . ' has a frame image and a thumbnail' . ($hasCount ? ' and a photo count' : '') . ' — add several, drag to reorder.')

@section('fields')
    @include('partials.category-select', ['selected' => $item->category_id, 'disabledIds' => $usedCategoryIds])

    @php
        $saved     = $item->pairs();
        $thumbUrls = $item->exists ? $item->imageUrls() : [];
        $frameUrls = $item->exists ? $item->frameUrls() : [];
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-1 mt-2">
        <label class="form-label m-0"><i class="bi bi-images me-1"></i>{{ ucfirst($noun) }} Images <small class="text-muted fw-normal">(Drag to reorder)</small></label>
        <span class="chip chip-brand">Total: <b id="pe-total">0</b></span>
    </div>
    <div class="webp-warning mb-2"><i class="bi bi-exclamation-triangle-fill"></i><span>Only <code>.webp</code> images are allowed for frames and thumbnails.</span></div>

    @error('frame_files')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    @error('frame_files.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    @error('thumb_files.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

    <div id="pe-rows" data-allow-drag>
        @foreach ($saved as $idx => $pair)
            <div class="pe-row {{ $hasCount ? 'has-count' : '' }}" draggable="true" data-row data-ref="{{ $idx }}">
                <span class="pe-num"></span>
                <input type="hidden" class="pe-slot" value="{{ $idx }}">
                <label class="pe-box filled" title="Click to replace">
                    <input type="file" class="pe-file" data-kind="frame" accept="image/webp" hidden>
                    <img class="pe-img" src="{{ $frameUrls[$idx] ?? '' }}" alt="">
                    <span class="pe-cap">Frame · <i class="bi bi-arrow-repeat"></i> replace</span>
                </label>
                <label class="pe-box filled" title="Click to replace">
                    <input type="file" class="pe-file" data-kind="thumbnail" accept="image/webp" hidden>
                    <img class="pe-img" src="{{ $thumbUrls[$idx] ?? '' }}" alt="">
                    <span class="pe-cap">Thumbnail · <i class="bi bi-arrow-repeat"></i> replace</span>
                </label>
                @if ($hasCount)
                    <div class="pe-count"><span class="fr-label">Count</span><input type="number" class="pe-count-input form-control form-control-sm" min="1" max="100" value="{{ $pair['count'] ?? 1 }}"></div>
                @endif
                <button type="button" class="pe-del" data-remove title="Remove"><i class="bi bi-x-lg"></i></button>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-soft btn-sm mt-1" id="pe-add"><i class="bi bi-plus-lg me-1"></i>Add image</button>

    <template id="pe-row-tpl">
        <div class="pe-row {{ $hasCount ? 'has-count' : '' }}" draggable="true" data-row data-ref="new">
            <span class="pe-num"></span>
            <input type="hidden" class="pe-slot" value="new">
            <label class="pe-box empty">
                <input type="file" class="pe-file" data-kind="frame" accept="image/webp" hidden>
                <img class="pe-img" alt="" hidden>
                <span class="pe-cap"><i class="bi bi-cloud-arrow-up"></i> Frame — click to upload</span>
            </label>
            <label class="pe-box empty">
                <input type="file" class="pe-file" data-kind="thumbnail" accept="image/webp" hidden>
                <img class="pe-img" alt="" hidden>
                <span class="pe-cap"><i class="bi bi-cloud-arrow-up"></i> Thumbnail — click to upload</span>
            </label>
            @if ($hasCount)
                <div class="pe-count"><span class="fr-label">Count</span><input type="number" class="pe-count-input form-control form-control-sm" min="1" max="100" value="1"></div>
            @endif
            <button type="button" class="pe-del" data-remove title="Remove"><i class="bi bi-x-lg"></i></button>
        </div>
    </template>

    <div class="section-divider my-3"></div>
    @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Inactive sets are hidden from the API.', 'checked' => old('status', $item->status)])
@endsection

@push('scripts')
<script>
(function () {
    const noun = @json($noun);
    const form = document.getElementById('item-form');
    const rowsEl = document.getElementById('pe-rows');
    const tpl = document.getElementById('pe-row-tpl');
    const totalEl = document.getElementById('pe-total');
    const isWebp = (f) => f && (f.type === 'image/webp' || /\.webp$/i.test(f.name || ''));

    function renumber() {
        const rows = [...rowsEl.querySelectorAll('.pe-row')];
        rows.forEach((r, i) => { r.querySelector('.pe-num').textContent = i + 1; });
        totalEl.textContent = rows.length;
    }
    // New rows go to the TOP so the latest-added frame shows first everywhere
    // (admin list, getCategories preview and the items API all follow this order).
    function addRow() {
        const frag = tpl.content.cloneNode(true);
        const row = frag.querySelector('.pe-row');
        rowsEl.prepend(frag);
        renumber();
        row?.scrollIntoView({ block: 'nearest' });
    }
    document.getElementById('pe-add').addEventListener('click', addRow);

    rowsEl.addEventListener('click', (e) => {
        const del = e.target.closest('[data-remove]');
        if (del) { e.preventDefault(); del.closest('.pe-row').remove(); renumber(); }
    });

    // File select → preview in the box (works even if the existing image became a <canvas>).
    rowsEl.addEventListener('change', (e) => {
        const input = e.target;
        if (!input.matches('.pe-file')) return;
        const file = input.files[0];
        const box = input.closest('.pe-box');
        if (file && !isWebp(file)) {
            input.value = '';
            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Only .webp images allowed', confirmButtonColor: '#4f46e5' });
            return;
        }
        if (!file) return;
        const old = box.querySelector('.pe-img') || box.querySelector('canvas');
        const img = new Image();
        img.className = 'pe-img';
        img.src = URL.createObjectURL(file);
        if (old) old.replaceWith(img); else box.insertBefore(img, box.querySelector('.pe-cap'));
        box.classList.remove('empty'); box.classList.add('filled');
    });

    // Drag reorder.
    let dragged = null;
    rowsEl.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('.pe-row');
        if (!dragged) return e.preventDefault();
        dragged.classList.add('dragging');
    });
    rowsEl.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; renumber(); });
    rowsEl.addEventListener('dragover', (e) => {
        if (!dragged) return;
        e.preventDefault();
        const over = e.target.closest('.pe-row');
        if (!over || over === dragged) return;
        const r = over.getBoundingClientRect();
        over.parentNode.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over);
    });

    // On submit: validate, then give inputs aligned keys in DOM order.
    form.addEventListener('submit', (e) => {
        const rows = [...rowsEl.querySelectorAll('.pe-row')];
        if (!rows.length) {
            e.preventDefault();
            if (window.Swal) Swal.fire({ icon: 'warning', title: `Add at least one ${noun}`, confirmButtonColor: '#4f46e5' });
            return;
        }
        for (const r of rows) {
            if (r.dataset.ref === 'new') {
                const ff = r.querySelector('.pe-file[data-kind=frame]').files[0];
                const tf = r.querySelector('.pe-file[data-kind=thumbnail]').files[0];
                if (!ff || !tf) {
                    e.preventDefault();
                    if (window.Swal) Swal.fire({ icon: 'warning', title: `Each new ${noun} needs a frame and a thumbnail`, confirmButtonColor: '#4f46e5' });
                    return;
                }
            }
        }
        rows.forEach((r, k) => {
            r.querySelector('.pe-slot').name = `slots[${k}]`;
            r.querySelector('.pe-file[data-kind=frame]').name = `frame_files[${k}]`;
            r.querySelector('.pe-file[data-kind=thumbnail]').name = `thumb_files[${k}]`;
            const c = r.querySelector('.pe-count-input');
            if (c) c.name = `counts[${k}]`;
        });
    });

    renumber();
    if (!rowsEl.querySelectorAll('.pe-row').length) addRow(); // start with one row on create
})();
</script>
@endpush

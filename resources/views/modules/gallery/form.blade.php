{{-- Shared create/edit for gallery modules (Frame, Baby Backdrop): one record, many images. --}}
@extends('layouts.module-form')
@section('form-sub', 'One ' . $noun . ' set per category. Add many images at once, drag to reorder, remove with ×.')

@section('fields')
    @include('partials.category-select', ['selected' => $item->category_id, 'disabledIds' => $usedCategoryIds])

    @include('partials.file-input', [
        'name' => 'new_images[]', 'label' => 'Add ' . $noun . ' images', 'accept' => 'image/webp',
        'required' => false, 'multiple' => true, 'hint' => 'Select several files at once · WebP only · max 10 MB each',
    ])

    <div class="d-flex align-items-center justify-content-between mb-2">
        <label class="form-label m-0">{{ ucfirst($noun) }}s <span class="text-muted fw-normal" id="img-count"></span></label>
        <small class="text-muted"><i class="bi bi-arrows-move"></i> Drag saved images to change the order</small>
    </div>
    <div class="gallery mb-3" id="gallery" data-allow-drag>
        {{-- Saved images are referenced by their position, never by their real path. --}}
        @php $saved = $item->images ?? []; $savedUrls = $item->exists ? $item->imageUrls() : []; @endphp
        @foreach (old('existing', array_keys($saved)) as $idx)
            @continue(!isset($saved[(int) $idx]))
            <div class="g-item" draggable="true" data-existing>
                <input type="hidden" name="existing[]" value="{{ (int) $idx }}">
                <img src="{{ $savedUrls[(int) $idx] ?? '' }}" alt="">
                <button type="button" class="g-del" title="Remove" data-remove><i class="bi bi-x-lg"></i></button>
                <span class="g-tag">Saved</span>
            </div>
        @endforeach
    </div>
    <div id="gallery-empty" class="empty-state border rounded-3 mb-3 py-4" hidden><i class="bi bi-images"></i>No images yet — add some above.</div>

    @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Inactive sets are hidden from the API.', 'checked' => old('status', $item->status)])
@endsection

@push('scripts')
<script>
(function () {
    const gallery = document.getElementById('gallery');
    const input = document.getElementById('f_new_images');
    const empty = document.getElementById('gallery-empty');
    const count = document.getElementById('img-count');
    let pending = [];

    function refresh() {
        const n = gallery.querySelectorAll('.g-item').length;
        empty.hidden = n > 0;
        count.textContent = n ? `(${n})` : '';
    }

    // Keep the <input> files in sync with the "pending" tiles (so removed new files are not uploaded).
    function syncInput() {
        const dt = new DataTransfer();
        pending.forEach((p) => dt.items.add(p.file));
        input.files = dt.files;
        const name = input.closest('.drop')?.querySelector('.fname');
        if (name) name.textContent = pending.length ? `${pending.length} new file(s) ready to upload` : '';
    }

    input.addEventListener('change', () => {
        // New tiles go to the TOP so the latest-added image shows first (matches the saved order).
        let anchor = gallery.firstChild;
        [...input.files].forEach((file) => {
            if (pending.some((p) => p.file === file)) return;
            const tile = document.createElement('div');
            tile.className = 'g-item new';
            tile.innerHTML = '<img alt=""><button type="button" class="g-del" title="Remove" data-remove><i class="bi bi-x-lg"></i></button><span class="g-tag">New</span>';
            tile.querySelector('img').src = URL.createObjectURL(file);
            gallery.insertBefore(tile, anchor);
            anchor = tile.nextSibling;
            pending.push({ file, tile });
        });
        syncInput();
        refresh();
    });

    gallery.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove]');
        if (!btn) return;
        const tile = btn.closest('.g-item');
        pending = pending.filter((p) => p.tile !== tile);
        tile.remove();
        syncInput();
        refresh();
    });

    // Drag & drop re-ordering of saved images.
    let dragged = null;
    gallery.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('.g-item[data-existing]');
        if (!dragged) return e.preventDefault();
        dragged.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    gallery.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; });
    gallery.addEventListener('dragover', (e) => {
        if (!dragged) return;
        e.preventDefault();
        const over = e.target.closest('.g-item[data-existing]');
        if (!over || over === dragged) return;
        const r = over.getBoundingClientRect();
        const after = (e.clientX - r.left) > r.width / 2;
        over.parentNode.insertBefore(dragged, after ? over.nextSibling : over);
    });

    refresh();
})();
</script>
@endpush

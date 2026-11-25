@extends('layouts.module-form')
@section('form-sub', 'Upload the video — its thumbnail is generated automatically in your browser with <canvas>.')

@section('fields')
    @include('partials.category-select', ['selected' => $item->category_id])
    <div class="row g-3">
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'video', 'label' => 'Video', 'accept' => 'video/mp4,video/webm,video/quicktime', 'required' => !$item->exists, 'hint' => 'MP4, WEBM or MOV · max 50 MB', 'image' => false])
            @if ($item->exists && $item->video)
                <video src="{{ $item->fileUrl('video') }}" controls preload="metadata" class="w-100 rounded-3 border" style="max-height:220px;background:#000"></video>
            @endif
        </div>
        <div class="col-md-6">
            <label class="form-label">Thumbnail <span class="chip chip-brand ms-1"><i class="bi bi-magic"></i>auto</span></label>
            <div class="preview-box flex-column align-items-start" id="thumb-box">
                @if ($item->thumbnail)
                    <img src="{{ $item->fileUrl('thumbnail') }}" alt="Current thumbnail" id="thumb-current">
                @else
                    <div class="thumb-empty" style="width:180px;height:110px" id="thumb-current">Select a video</div>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2 mt-2">
                <label class="small text-muted mb-0" for="thumb-at">Frame at</label>
                <input type="range" class="form-range" id="thumb-at" min="0" max="95" value="25" style="max-width:180px" disabled>
                <span class="small mono" id="thumb-at-label">25%</span>
            </div>
            <div class="hint" id="thumb-status">The frame at 25% of the video becomes the thumbnail. Move the slider to pick another frame.</div>
            <input type="hidden" name="thumbnail_data" id="thumbnail_data">
        </div>
        <div class="col-12">
            <label class="form-label" for="prompt">Prompt <span class="req">*</span></label>
            <textarea class="form-control @error('prompt') is-invalid @enderror" id="prompt" name="prompt" rows="5" required maxlength="10000" placeholder="Describe the video the AI should generate…">{{ old('prompt', $item->prompt) }}</textarea>
        </div>
    </div>
    @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Only active videos are returned by the API.', 'checked' => old('status', $item->status)])
@endsection

@push('scripts')
<script src="{{ asset('js/video-thumbnail.js') }}?v=3"></script>
<script>
(function () {
    const input = document.getElementById('f_video');
    const box = document.getElementById('thumb-box');
    const hidden = document.getElementById('thumbnail_data');
    const status = document.getElementById('thumb-status');
    const slider = document.getElementById('thumb-at');
    const label = document.getElementById('thumb-at-label');
    const submit = document.getElementById('submit-btn');
    let file = null, busy = 0;

    async function generate() {
        if (!file) return;
        const run = ++busy;
        submit.disabled = true;
        status.textContent = 'Generating thumbnail…';
        try {
            const at = Math.max(0.01, slider.value / 100);
            const dataUrl = await captureVideoFrame(file, { at });
            if (run !== busy) return;
            hidden.value = dataUrl;
            box.innerHTML = '';
            const img = new Image();
            img.src = dataUrl;
            img.alt = 'Generated thumbnail';
            box.appendChild(img);
            status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Thumbnail ready.</span>';
        } catch (e) {
            if (run !== busy) return;
            hidden.value = '';
            status.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + e.message + ' — the video will be saved without a thumbnail.</span>';
        } finally {
            if (run === busy) submit.disabled = false;
        }
    }

    input.addEventListener('change', () => {
        file = input.files[0] || null;
        slider.disabled = !file;
        if (file) generate();
    });
    slider.addEventListener('input', () => { label.textContent = slider.value + '%'; });
    slider.addEventListener('change', generate);
})();
</script>
@endpush

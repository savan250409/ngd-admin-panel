@extends('layouts.module-form')
@section('form-sub', 'A sample image and the AI prompt used to create it.')

@section('fields')
    @include('partials.category-select', ['selected' => $item->category_id])
    <div class="row g-3">
        <div class="col-md-5">
            @include('partials.file-input', ['name' => 'image', 'label' => 'Image', 'accept' => 'image/webp', 'required' => !$item->exists, 'hint' => 'WebP only · max 10 MB', 'current' => $item->image, 'current_url' => $item->fileUrl('image')])
        </div>
        <div class="col-md-7">
            <label class="form-label" for="prompt">Prompt <span class="req">*</span></label>
            <textarea class="form-control @error('prompt') is-invalid @enderror" id="prompt" name="prompt" rows="9" required maxlength="10000" placeholder="Describe the image the AI should generate…">{{ old('prompt', $item->prompt) }}</textarea>
            <div class="hint"><span id="prompt-count">0</span> / 10000 characters</div>
        </div>
    </div>
    @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Only active items are returned by the API.', 'checked' => old('status', $item->status)])
@endsection

@push('scripts')
<script>
    (function () {
        const t = document.getElementById('prompt'), c = document.getElementById('prompt-count');
        const upd = () => c.textContent = t.value.length;
        t.addEventListener('input', upd); upd();
    })();
</script>
@endpush

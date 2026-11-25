@extends('layouts.module-form')
@section('form-sub', 'Upload the template zip (Lottie data.json or data.html, optional template.json) and a thumbnail.')

@section('fields')
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="title">Title <span class="req">*</span></label>
            <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="191" placeholder="Happy Birthday Story">
        </div>
        <div class="col-md-6">@include('partials.category-select', ['selected' => $item->category_id])</div>
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'zip', 'label' => 'Template zip', 'accept' => '.zip,application/zip', 'required' => !$item->exists, 'hint' => 'Must contain data.json or data.html · max 200 MB', 'current' => $item->zip, 'image' => false])
            @if ($item->exists)
                <div class="d-flex flex-wrap gap-1">
                    @if ($item->video_html_path)<span class="chip chip-ok"><i class="bi bi-play-circle"></i>Player ready</span>@endif
                    @if ($item->width)<span class="chip">{{ $item->width }}×{{ $item->height }}</span>@endif
                    @if (!is_null($item->total_image_count))<span class="chip chip-brand">{{ $item->total_image_count }} elements</span>@endif
                </div>
            @endif
        </div>
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'thumbnail', 'label' => 'Thumbnail', 'accept' => 'image/webp', 'required' => !$item->exists, 'hint' => 'WebP only · max 10 MB', 'current' => $item->thumbnail, 'current_url' => $item->fileUrl('thumbnail')])
        </div>
        <div class="col-md-6">
            @include('partials.switch-row', ['name' => 'is_paid', 'title' => 'Premium', 'desc' => 'Returned as is_paid: true in the API.', 'checked' => old('is_paid', $item->is_paid)])
        </div>
        <div class="col-md-6">
            @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Only active templates are returned by the API.', 'checked' => old('status', $item->status)])
        </div>
    </div>
@endsection

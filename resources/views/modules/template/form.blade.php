@extends('layouts.module-form')
@section('form-sub', 'Template frame, thumbnail and how many photos it holds.')

@section('fields')
    <div class="row g-3">
        <div class="col-md-8">@include('partials.category-select', ['selected' => $item->category_id])</div>
        <div class="col-md-4">
            <label class="form-label" for="count">Count <span class="req">*</span></label>
            <input type="number" class="form-control @error('count') is-invalid @enderror" id="count" name="count" min="1" max="100" required value="{{ old('count', $item->count ?? 1) }}">
            <div class="hint">Number of photo slots in this template.</div>
        </div>
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'frame', 'label' => 'Frame', 'accept' => 'image/webp', 'required' => !$item->exists, 'hint' => 'Transparent WebP · max 10 MB', 'current' => $item->frame, 'current_url' => $item->fileUrl('frame')])
        </div>
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'thumbnail', 'label' => 'Thumbnail', 'accept' => 'image/webp', 'required' => !$item->exists, 'hint' => 'WebP only · max 5 MB', 'current' => $item->thumbnail, 'current_url' => $item->fileUrl('thumbnail')])
        </div>
    </div>
    @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Only active templates are returned by the API.', 'checked' => old('status', $item->status)])
@endsection

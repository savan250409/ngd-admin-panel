@extends('layouts.module-form')
@section('form-sub', 'Upload the layered frame zip (it must contain a .json layout) and its thumbnail.')

@section('fields')
    <div class="row g-3">
        <div class="col-md-8">@include('partials.category-select', ['selected' => $item->category_id])</div>
        <div class="col-md-4">
            <label class="form-label" for="aspect_ratio">Aspect ratio <span class="req">*</span></label>
            <select class="form-select @error('aspect_ratio') is-invalid @enderror" name="aspect_ratio" id="aspect_ratio" required>
                <option value="">— Select —</option>
                @foreach (\App\Models\DynamicFrame::RATIOS as $r)
                    <option value="{{ $r }}" @selected(old('aspect_ratio', $item->aspect_ratio) === $r)>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'zip', 'label' => 'Zip', 'accept' => '.zip,application/zip', 'required' => !$item->exists, 'hint' => 'Zip with the JSON layout + assets · max 100 MB', 'current' => $item->zip, 'image' => false])
            @if ($item->exists && $item->json)
                @php $layers = count(json_decode($item->json, true)['layers'] ?? []); @endphp
                <div class="chip chip-ok"><i class="bi bi-filetype-json"></i>JSON loaded · {{ $layers }} layers</div>
            @endif
        </div>
        <div class="col-md-6">
            @include('partials.file-input', ['name' => 'thumbnail', 'label' => 'Thumbnail', 'accept' => 'image/webp', 'required' => !$item->exists, 'hint' => 'WebP only · max 10 MB', 'current' => $item->thumbnail, 'current_url' => $item->fileUrl('thumbnail')])
        </div>
        <div class="col-md-6">
            @include('partials.switch-row', ['name' => 'is_paid', 'title' => 'Paid content', 'desc' => 'Returned as is_paid: true in the API.', 'checked' => old('is_paid', $item->is_paid)])
        </div>
        <div class="col-md-6">
            @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Only active frames are returned by the API.', 'checked' => old('status', $item->status)])
        </div>
    </div>
@endsection

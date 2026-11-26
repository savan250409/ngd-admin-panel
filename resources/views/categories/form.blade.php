@extends('layouts.app')
@section('title', ($category->exists ? 'Edit ' : 'Add ') . $cfg['title'] . ' Category')
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / {{ $cfg['title'] }} / <a href="{{ route('categories.index', $module) }}">Categories</a> / {{ $category->exists ? 'Edit' : 'Add' }} @endsection

@section('content')
    <div class="panel form-panel">
        <div class="panel-head">
            <div>
                <h2>{{ $category->exists ? $category->name : 'New ' . strtolower($cfg['title']) . ' category' }}</h2>
                <div class="sub">Category name, image and the apps it is served to (pick one or many).</div>
            </div>
            <a href="{{ route('categories.index', $module) }}" class="btn btn-light border ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
        <form method="POST" enctype="multipart/form-data"
              action="{{ $category->exists ? route('categories.update', [$module, $category->id]) : route('categories.store', $module) }}">
            @csrf
            @if ($category->exists) @method('PUT') @endif
            <div class="panel-body">
                @include('partials.errors')
                <div class="row g-3">
                    <div class="col-12">
                        @php $selectedApps = old('app_ids', $category->exists ? $category->apps->pluck('id')->all() : []); @endphp
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <label class="form-label mb-1">Apps <span class="req">*</span> <span class="text-muted fw-normal">— this category is served to every app you tick</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="chip chip-brand" id="app-count">0 selected</span>
                                @if ($apps->count() > 1)
                                    <button type="button" class="btn btn-sm btn-link p-0" data-apps-all>Select all</button>
                                    <span class="text-muted">·</span>
                                    <button type="button" class="btn btn-sm btn-link p-0" data-apps-none>Clear</button>
                                @endif
                            </div>
                        </div>
                        @error('app_ids')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                        @if ($apps->isEmpty())
                            <div class="hint text-warning"><i class="bi bi-exclamation-triangle"></i> No apps yet — <a href="{{ route('apps.create') }}">add an app</a> first.</div>
                        @else
                            @if ($apps->count() > 6)
                                <div class="app-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="app-search" class="form-control" placeholder="Search app or package name…" autocomplete="off">
                                </div>
                            @endif
                            <div class="app-picker {{ $apps->count() > 6 ? 'app-picker-scroll' : '' }}" id="app-picker">
                                @foreach ($apps as $a)
                                    <label class="app-option" data-search="{{ strtolower($a->app_name . ' ' . $a->package_name) }}">
                                        <input type="checkbox" name="app_ids[]" value="{{ $a->id }}" @checked(in_array($a->id, $selectedApps))>
                                        <span class="app-option-body">
                                            <span class="app-option-name">{{ $a->app_name }}</span>
                                            <span class="app-option-pkg mono">{{ $a->package_name }}</span>
                                        </span>
                                        <i class="bi bi-check-circle-fill app-option-check"></i>
                                    </label>
                                @endforeach
                            </div>
                            <div id="app-noresult" class="text-muted small mt-2" hidden><i class="bi bi-search me-1"></i>No apps match your search.</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name">Category name <span class="req">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="150" placeholder="e.g. Birthday">
                    </div>

                    @if (!empty($cfg['category_orientation']))
                        <div class="col-md-6">
                            <label class="form-label">Orientation <span class="req">*</span></label>
                            <div class="d-flex gap-2">
                                @foreach (['portrait' => 'phone', 'landscape' => 'aspect-ratio'] as $o => $icon)
                                    <input type="radio" class="btn-check" name="orientation" id="o_{{ $o }}" value="{{ $o }}" @checked(old('orientation', $category->orientation ?: 'portrait') === $o)>
                                    <label class="btn btn-outline-secondary flex-fill" for="o_{{ $o }}"><i class="bi bi-{{ $icon }} me-1"></i>{{ ucfirst($o) }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6">
                        @include('partials.file-input', [
                            'name' => 'image', 'label' => 'Category image', 'accept' => 'image/webp',
                            'required' => !$category->exists, 'hint' => 'WebP only · max 5 MB', 'current' => $category->image, 'current_url' => $category->imageUrl(),
                        ])
                    </div>
                    <div class="col-md-6">
                        @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Inactive categories are hidden from the API.', 'checked' => old('status', $category->status)])
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $category->exists ? 'Update category' : 'Save category' }}</button>
                <a href="{{ route('categories.index', $module) }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const picker = document.getElementById('app-picker');
        if (!picker) return;
        const boxes = () => [...picker.querySelectorAll('input[type=checkbox]')];
        const countEl = document.getElementById('app-count');
        const search = document.getElementById('app-search');
        const noRes = document.getElementById('app-noresult');

        function updateCount() {
            if (countEl) countEl.textContent = boxes().filter((b) => b.checked).length + ' selected';
        }

        // Select all / Clear — "Select all" ticks only the apps currently shown by the search.
        document.querySelector('[data-apps-all]')?.addEventListener('click', () => {
            picker.querySelectorAll('.app-option:not([hidden]) input').forEach((b) => (b.checked = true));
            updateCount();
        });
        document.querySelector('[data-apps-none]')?.addEventListener('click', () => {
            boxes().forEach((b) => (b.checked = false));
            updateCount();
        });
        picker.addEventListener('change', updateCount);

        // Filter the picker as you type.
        search?.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            let visible = 0;
            picker.querySelectorAll('.app-option').forEach((opt) => {
                const hit = !q || (opt.dataset.search || '').includes(q);
                opt.hidden = !hit;
                if (hit) visible++;
            });
            if (noRes) noRes.hidden = visible > 0;
        });

        updateCount();
    })();
</script>
@endpush

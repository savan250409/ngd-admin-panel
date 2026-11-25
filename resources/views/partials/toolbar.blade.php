{{--
    Search + filters for a list page. Changes reload #list-area over AJAX.
    @param string $action
    @param string $placeholder
    @param \Illuminate\Support\Collection|null $apps
    @param \Illuminate\Support\Collection|null $categories
--}}
<form class="toolbar" method="GET" action="{{ $action }}" data-list-form data-target="#list-area" role="search">
    <div class="search">
        <i class="bi bi-search"></i>
        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ $placeholder ?? 'Search…' }}" autocomplete="off">
    </div>

    @isset($apps)
        <select name="app_id" class="form-select" data-app-filter aria-label="Filter by app">
            <option value="">All apps</option>
            @foreach ($apps as $a)
                <option value="{{ $a->id }}" @selected(request('app_id') == $a->id)>{{ $a->app_name }}</option>
            @endforeach
        </select>
    @endisset

    @isset($categories)
        <select name="category_id" class="form-select" data-category-filter aria-label="Filter by category">
            <option value="">All categories</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" data-apps="{{ $c->apps->pluck('id')->implode(',') }}" @selected(request('category_id') == $c->id)>
                    {{ $c->name }}@if ($c->apps->isNotEmpty()) — {{ $c->apps->pluck('app_name')->implode(', ') }}@endif
                </option>
            @endforeach
        </select>
    @endisset

    <select name="status" class="form-select" aria-label="Filter by status">
        <option value="">Any status</option>
        <option value="1" @selected(request('status') === '1')>Active</option>
        <option value="0" @selected(request('status') === '0')>Inactive</option>
    </select>

    <select name="per_page" class="form-select" style="min-width:110px" aria-label="Rows per page">
        @foreach ([10, 25, 50, 100] as $n)
            <option value="{{ $n }}" @selected((int) request('per_page', 10) === $n)>{{ $n }} / page</option>
        @endforeach
    </select>

    <button type="button" class="btn btn-light border" data-reset title="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></button>
</form>

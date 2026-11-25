@extends('layouts.app')
@section('title', $cfg['title'] . ' Categories')
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / {{ $cfg['title'] }} / Categories @endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $cfg['title'] }} categories</h2>
                <div class="sub">{{ $categories->total() }} total · a category can be served to several apps</div>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route($module . '.items.index') }}" class="btn btn-soft"><i class="bi {{ $cfg['icon'] }} me-1"></i>{{ $cfg['item_title'] }}</a>
                <a href="{{ route('categories.create', $module) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add category</a>
            </div>
        </div>

        @include('partials.toolbar', ['action' => route('categories.index', $module), 'placeholder' => 'Search category name or #id…', 'apps' => $apps])

        <div id="list-area">
            <div class="table-responsive">
                <table class="table table-x">
                    <thead>
                    <tr>
                        <th>#</th><th>Image</th><th>Category</th><th>Apps</th>
                        @if (!empty($cfg['category_orientation']))<th>Orientation</th>@endif
                        <th>{{ $cfg['item_title'] }}</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($categories as $c)
                        <tr>
                            <td class="id-badge">{{ $c->id }}</td>
                            <td>@include('partials.thumb', ['url' => $c->imageUrl()])</td>
                            <td class="fw-semibold">{{ $c->name }}</td>
                            <td>
                                @if ($c->apps->isEmpty())
                                    <span class="chip chip-warn" title="No app assigned — hidden from every API">Unassigned</span>
                                @else
                                    <div class="app-chips">
                                        @foreach ($c->apps as $a)
                                            <span class="chip chip-brand" title="{{ $a->package_name }}">{{ $a->app_name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            @if (!empty($cfg['category_orientation']))
                                <td><span class="chip"><i class="bi bi-{{ $c->orientation === 'landscape' ? 'aspect-ratio' : 'phone' }}"></i>{{ ucfirst($c->orientation) }}</span></td>
                            @endif
                            <td>
                                <a href="{{ route($module . '.items.index', ['category_id' => $c->id]) }}" class="chip chip-brand text-decoration-none">{{ $c->items_count }} {{ !empty($cfg['gallery']) ? 'set' : 'items' }}</a>
                            </td>
                            <td>@include('partials.switch', ['url' => route('categories.toggle', [$module, $c->id]), 'value' => $c->status])</td>
                            <td>@include('partials.actions', [
                                'edit' => route('categories.edit', [$module, $c->id]),
                                'delete' => route('categories.destroy', [$module, $c->id]),
                                'confirm' => 'Delete the category "' . $c->name . '"?',
                            ])</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-folder2-open"></i>No categories found.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pager', ['p' => $categories])
        </div>
    </div>
@endsection

{{-- Shared list page for a module's items. Children fill "thead" and "tbody" (and optionally "summary"). --}}
@extends('layouts.app')
@section('title', $cfg['item_title'])
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / {{ $cfg['title'] }} / {{ $cfg['item_title'] }} @endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $cfg['item_title'] }}</h2>
                <div class="sub">@hasSection('summary') @yield('summary') @else {{ $items->total() }} total @endif</div>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('categories.index', $module) }}" class="btn btn-soft"><i class="bi bi-folder2 me-1"></i>Categories</a>
                <a href="{{ route($module . '.items.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add {{ strtolower($cfg['item_singular']) }}</a>
            </div>
        </div>

        @include('partials.toolbar', [
            'action' => route($module . '.items.index'),
            'placeholder' => $searchPlaceholder ?? 'Search category or #id…',
            'apps' => $apps,
            'categories' => $categories,
        ])

        <div id="list-area">
            <div class="table-responsive">
                <table class="table table-x">
                    <thead><tr>@yield('thead')</tr></thead>
                    <tbody>@yield('tbody')</tbody>
                </table>
            </div>
            @include('partials.pager', ['p' => $items])
        </div>
    </div>
@endsection

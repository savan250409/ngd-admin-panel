{{-- Shared create/edit page for a module's items. Children fill "fields". --}}
@extends('layouts.app')
@section('title', ($item->exists ? 'Edit ' : 'Add ') . $cfg['item_singular'])
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / {{ $cfg['title'] }} / <a href="{{ route($module . '.items.index') }}">{{ $cfg['item_title'] }}</a> / {{ $item->exists ? 'Edit #' . $item->id : 'Add' }} @endsection

@section('content')
    <div class="panel form-panel">
        <div class="panel-head">
            <div>
                <h2>{{ $item->exists ? 'Edit ' . strtolower($cfg['item_singular']) . ' #' . $item->id : 'New ' . strtolower($cfg['item_singular']) }}</h2>
                <div class="sub">@yield('form-sub', 'Fields marked * are required.')</div>
            </div>
            <a href="{{ route($module . '.items.index') }}" class="btn btn-light border ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
        <form method="POST" enctype="multipart/form-data" id="item-form"
              action="{{ $item->exists ? route($module . '.items.update', $item->id) : route($module . '.items.store') }}">
            @csrf
            @if ($item->exists) @method('PUT') @endif
            <div class="panel-body">
                @include('partials.errors')
                @yield('fields')
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" id="submit-btn"><i class="bi bi-check2 me-1"></i>{{ $item->exists ? 'Update' : 'Save' }} {{ strtolower($cfg['item_singular']) }}</button>
                <a href="{{ route($module . '.items.index') }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>
    </div>
@endsection

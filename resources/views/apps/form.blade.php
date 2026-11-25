@extends('layouts.app')
@section('title', $app->exists ? 'Edit App' : 'Add App')
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / <a href="{{ route('apps.index') }}">Apps</a> / {{ $app->exists ? 'Edit' : 'Add' }} @endsection

@section('content')
    <div class="panel form-panel">
        <div class="panel-head">
            <div>
                <h2>{{ $app->exists ? 'Edit ' . $app->app_name : 'New app' }}</h2>
                <div class="sub">Your mobile apps send this package name to the API to get their own content.</div>
            </div>
            <a href="{{ route('apps.index') }}" class="btn btn-light border ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
        <form method="POST" action="{{ $app->exists ? route('apps.update', $app->id) : route('apps.store') }}">
            @csrf
            @if ($app->exists) @method('PUT') @endif
            <div class="panel-body">
                @include('partials.errors')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="app_name">App name <span class="req">*</span></label>
                        <input class="form-control @error('app_name') is-invalid @enderror" id="app_name" name="app_name" value="{{ old('app_name', $app->app_name) }}" placeholder="Baby Photo Frames" required maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="package_name">Package name <span class="req">*</span></label>
                        <input class="form-control mono @error('package_name') is-invalid @enderror" id="package_name" name="package_name" value="{{ old('package_name', $app->package_name) }}" placeholder="com.company.babyframes" required maxlength="191">
                        <div class="hint">Must be unique, e.g. <span class="mono">com.company.app</span></div>
                    </div>
                    <div class="col-12">
                        @include('partials.switch-row', ['name' => 'status', 'title' => 'Active', 'desc' => 'Inactive apps get "Invalid package name" from every API.', 'checked' => old('status', $app->status)])
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $app->exists ? 'Update app' : 'Save app' }}</button>
                <a href="{{ route('apps.index') }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>
    </div>
@endsection

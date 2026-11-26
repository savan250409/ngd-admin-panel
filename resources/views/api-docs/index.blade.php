@extends('layouts.app')
@section('title', 'API List')
@section('crumbs')<a href="{{ route('dashboard') }}">Overview</a> / Developer / API List @endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="panel h-100">
                <div class="panel-head">
                    <div>
                        <h2><i class="bi bi-shield-lock me-1"></i>Bearer token</h2>
                        <div class="sub">Send it with every request. Requests without it (or with an old one) get <span class="mono">401 Unauthorized</span>.</div>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="token-box mb-3">
                        <i class="bi bi-key"></i>
                        <code id="api-token" data-value="{{ $token }}">{{ str_repeat('•', 48) }}</code>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="reveal-token" title="Show / hide"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="#api-token" title="Copy"><i class="bi bi-copy"></i></button>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <form method="POST" action="{{ route('api-docs.token') }}" data-confirm="All apps using the current token will stop working until you update them.">
                            @csrf
                            <button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Generate new token</button>
                        </form>
                        <a href="{{ route('api-docs.postman') }}" class="btn btn-soft"><i class="bi bi-box-arrow-down me-1"></i>Postman collection</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="panel h-100">
                <div class="panel-head"><h2><i class="bi bi-info-circle me-1"></i>How it works</h2></div>
                <div class="panel-body small">
                    <div class="mb-2"><b>Base URL</b>
                        <div class="ep-url m-0 mt-1"><code>{{ $baseUrl }}</code><button class="btn btn-sm btn-link p-0" data-copy="{{ $baseUrl }}"><i class="bi bi-copy"></i></button></div>
                    </div>
                    <ul class="ps-3 mb-0 text-muted">
                        <li>All endpoints are <b>POST</b> (form-data or JSON body).</li>
                        <li>Header: <span class="mono">Authorization: Bearer &lt;token&gt;</span></li>
                        <li><span class="mono">package_name</span> decides which app's data is returned — other apps' categories are never exposed.</li>
                        <li>Only <b>active</b> apps, categories and items are returned.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Endpoints</h2>
                <div class="sub">{{ count($groups) * 2 }} endpoints · 2 per module</div>
            </div>
        </div>
        <div class="panel-body">
            <ul class="nav nav-pills module-tabs gap-1 mb-4 flex-wrap" role="tablist">
                @foreach ($groups as $i => $g)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#api-{{ $g['key'] }}" type="button" role="tab">
                            <i class="bi {{ $g['icon'] }} me-1"></i>{{ $g['title'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach ($groups as $i => $g)
                    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="api-{{ $g['key'] }}" role="tabpanel">
                        <div class="row g-3">
                            @foreach ($g['endpoints'] as $n => $ep)
                                @php $url = $baseUrl . '/' . $ep['path']; @endphp
                                <div class="col-xl-6">
                                    <div class="ep">
                                        <div class="ep-head">
                                            <span class="method">POST</span>
                                            <h3>{{ $n + 1 }}. {{ $ep['name'] }}</h3>
                                        </div>
                                        <div class="ep-url">
                                            <code>{{ $url }}</code>
                                            <button class="btn btn-sm btn-link p-0" data-copy="{{ $url }}" title="Copy URL"><i class="bi bi-copy"></i></button>
                                        </div>
                                        <div class="ep-body">
                                            <p class="text-muted small mb-0">{{ $ep['description'] }}</p>

                                            <h4>Headers</h4>
                                            <div class="param"><code>Authorization</code><span class="text-muted">Bearer &lt;token&gt;</span><span class="chip ms-auto">required</span></div>

                                            <h4>Body</h4>
                                            @foreach ($ep['params'] as $p)
                                                <div class="param"><code>{{ $p['key'] }}</code><span class="text-muted">{{ $p['type'] }}</span><span class="chip ms-auto">required</span></div>
                                            @endforeach

                                            <h4>Sample response</h4>
                                            <pre class="json">{{ json_encode($ep['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

                                            <h4>cURL</h4>
                                            @php
                                                $curl = "curl -X POST \"{$url}\" \\\n  -H \"Authorization: Bearer <token>\" \\\n  -H \"Accept: application/json\"";
                                                foreach ($ep['params'] as $p) { $curl .= " \\\n  -F \"{$p['key']}={$p['example']}\""; }
                                            @endphp
                                            <div class="code-wrap">
                                                <pre class="json">{{ $curl }}</pre>
                                                <button type="button" class="code-copy" data-copy="{{ $curl }}" title="Copy cURL"><i class="bi bi-copy"></i><span>Copy</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const code = document.getElementById('api-token');
        const btn = document.getElementById('reveal-token');
        let shown = false;
        btn.addEventListener('click', () => {
            shown = !shown;
            code.textContent = shown ? code.dataset.value : '•'.repeat(48);
            btn.firstElementChild.className = shown ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    })();
</script>
@endpush

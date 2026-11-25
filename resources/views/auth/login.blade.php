<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}?v=5" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <section class="auth-art">
        <div class="d-flex align-items-center gap-3">
            <span class="brand-mark"><i class="bi bi-hexagon-half"></i></span>
            <b class="fs-5">NGD Admin</b>
        </div>
        <div>
            <h1>Manage every frame, template and AI asset in one place.</h1>
            <p class="opacity-75 mb-4">Apps, categories, dynamic frames, backdrops, video templates and AI content — served to your apps through secure APIs.</p>
            <div class="tiles">
                <span><i class="bi bi-bounding-box-circles"></i></span>
                <span><i class="bi bi-grid-1x2"></i></span>
                <span><i class="bi bi-layers"></i></span>
                <span><i class="bi bi-balloon-heart"></i></span>
                <span><i class="bi bi-camera-reels"></i></span>
                <span><i class="bi bi-stars"></i></span>
                <span><i class="bi bi-film"></i></span>
                <span><i class="bi bi-braces-asterisk"></i></span>
            </div>
        </div>
        <small class="opacity-50">&copy; {{ date('Y') }} NGD</small>
    </section>

    <section class="auth-form">
        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <h2 class="fw-bold mb-1">Welcome back</h2>
            <p class="text-muted mb-4">Sign in to the admin panel.</p>

            @if (session('error'))
                <div class="alert alert-danger border-0 py-2"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="alert alert-success border-0 py-2">{{ session('success') }}</div>
            @endif

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="current-password">
                    <button class="btn btn-light border" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.firstElementChild.classList.toggle('bi-eye');this.firstElementChild.classList.toggle('bi-eye-slash');" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
                @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label" for="remember">Keep me signed in</label>
            </div>
            <button class="btn btn-primary btn-lg w-100">Sign in <i class="bi bi-arrow-right ms-1"></i></button>
        </form>
    </section>
</div>
</body>
</html>

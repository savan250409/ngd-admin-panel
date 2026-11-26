<?php

use App\Http\Controllers\Admin\AiVideoController;
use App\Http\Controllers\Admin\ApiDocsController;
use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SecureMediaController;
use App\Http\Controllers\Admin\VideoTemplateController;
use App\Http\Controllers\PublicMediaController;
use App\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Public media gate for the mobile APIs — streams an upload by its opaque token only
// (no admin session). The API hands apps "/media?token=..." instead of real paths.
Route::get('media', PublicMediaController::class)->name('public-media');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Streams media by opaque token (the real path never reaches the browser).
    Route::get('secure-media', SecureMediaController::class)->name('secure-media');

    // API List (no real media on this page, so it is not rewritten).
    Route::get('api-list', [ApiDocsController::class, 'index'])->name('api-docs.index');
    Route::post('api-list/token', [ApiDocsController::class, 'regenerate'])->name('api-docs.token');
    Route::get('api-list/postman', [ApiDocsController::class, 'postman'])->name('api-docs.postman');

    Route::middleware('secure.media')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // ── Apps ──────────────────────────────────────────────────────────
        Route::post('apps/{id}/toggle', [AppController::class, 'toggle'])->name('apps.toggle');
        Route::resource('apps', AppController::class)->except('show')->parameters(['apps' => 'id']);

        // ── Categories (one controller for every module) ─────────────────
        Route::prefix('{module}/categories')->whereIn('module', ModuleRegistry::keys())
            ->name('categories.')->controller(CategoryController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('reorder', 'reorder')->name('reorder');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{id}/edit', 'edit')->name('edit');
                Route::put('{id}', 'update')->name('update');
                Route::delete('{id}', 'destroy')->name('destroy');
                Route::post('{id}/toggle', 'toggle')->name('toggle');
            });

        // ── Module items (frames, templates, backdrops, ...) ─────────────
        foreach (ModuleRegistry::all() as $key => $cfg) {
            $slug = $cfg['item_slug'];
            Route::prefix($key)->name("{$key}.items.")->controller($cfg['item_controller'])->group(function () use ($slug) {
                Route::get($slug, 'index')->name('index');
                Route::get("{$slug}/create", 'create')->name('create');
                Route::post($slug, 'store')->name('store');
                Route::get("{$slug}/{id}/edit", 'edit')->name('edit');
                Route::put("{$slug}/{id}", 'update')->name('update');
                Route::delete("{$slug}/{id}", 'destroy')->name('destroy');
                Route::post("{$slug}/{id}/toggle", 'toggle')->name('toggle');
            });
        }

        Route::post('ai-video/videos/{id}/thumbnail', [AiVideoController::class, 'saveThumbnail'])->name('ai-video.items.thumbnail');
    });

    // Preview player for a video template, proxied so the real upload path stays hidden.
    Route::get('video-template/templates/{id}/player/{path}', [VideoTemplateController::class, 'player'])
        ->where('path', '.*')->name('video-template.items.player');

    Route::middleware('secure.media')->group(function () {
    });
});

<?php

use App\Http\Controllers\Api\ContentApiController;
use App\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;

/*
 | Every request needs:  Authorization: Bearer <token from the API List screen>
 | Two POST endpoints per module, e.g. for "frame":
 |   POST /api/v1/frame/getCategories          body: package_name
 |   POST /api/v1/frame/getFramesByCategory    body: package_name, category_id
 */
Route::prefix('v1')->middleware(['api.token', 'throttle:240,1', 'secure.media:api'])->group(function () {
    foreach (ModuleRegistry::all() as $key => $cfg) {
        Route::post("{$key}/getCategories", [ContentApiController::class, 'categories'])
            ->defaults('module', $key)->name("api.{$key}.categories");

        Route::post("{$key}/{$cfg['api_items']}", [ContentApiController::class, 'items'])
            ->defaults('module', $key)->name("api.{$key}.items");
    }
});

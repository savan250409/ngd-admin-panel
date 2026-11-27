<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Two POST endpoints per module:
 *   /api/v1/{module}/getCategories        body: package_name (+ optional platform)
 *   /api/v1/{module}/get...ByCategory     body: category_id
 * getCategories is app-scoped (only the package's own categories). The items
 * endpoint is addressed by category id alone — the app already got its ids there.
 */
class ContentApiController extends Controller
{
    public function categories(Request $request, string $module): JsonResponse
    {
        $cfg = ModuleRegistry::get($module);

        if ($error = $this->validate($request, [
            'package_name' => 'required|string|max:191',
            'platform'     => 'nullable|string|in:android,ios',
        ])) {
            return $error;
        }
        if (!$app = $this->app($request)) {
            return $this->fail('Invalid package name.', 404);
        }

        $categories = $cfg['category_model']::query()
            ->whereHas('apps', fn ($a) => $a->where('apps.id', $app->id))
            ->active()
            ->withCount(['items as active_items_count' => fn ($q) => $q->where('status', true)])
            ->ordered() // manual sequence from the Index popup (new categories first)
            ->get();

        // Each category also carries a preview of its latest 5 items.
        $data = $categories->map(function ($category) use ($module) {
            return $category->toApiArray() + ['items' => $this->latestItems($category, $module)];
        })->values();

        return response()->json([
            'status'  => true,
            'message' => $categories->isEmpty() ? 'No categories found.' : 'Categories fetched successfully.',
            'app'     => ['app_name' => $app->app_name, 'package_name' => $app->package_name],
            'data'    => $data,
        ]);
    }

    /** The 5 most recent active items of a category (for the getCategories preview). */
    private function latestItems($category, string $module): array
    {
        // Gallery modules (Frame, Baby Backdrop) keep one record per category → preview its first 5 images,
        // in the exact order they are arranged in the editor (same order the admin list and items API show).
        if (!empty(ModuleRegistry::get($module)['gallery'])) {
            $record = $category->items()->active()->first();

            return $record ? array_slice($record->apiImages(), 0, 5) : [];
        }

        // Newest added / most recently edited first — identical to the admin item list.
        return $category->items()->active()->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get()
            ->map->toApiArray()->values()->all();
    }

    public function items(Request $request, string $module): JsonResponse
    {
        $cfg = ModuleRegistry::get($module);

        // Items are addressed by category id alone — no package name needed. The app
        // already learned its category ids from getCategories (which IS app-scoped).
        if ($error = $this->validate($request, [
            'category_id' => 'required|integer',
        ])) {
            return $error;
        }

        $category = $cfg['category_model']::query()
            ->where('id', $request->integer('category_id'))
            ->active()
            ->first();
        if (!$category) {
            return $this->fail('Category not found.', 404);
        }

        // Same order as the admin item list: newest added / most recently edited first.
        $items = $category->items()->active()->orderByDesc('updated_at')->orderByDesc('id')->get();

        // Gallery modules store one image list per category: return its flat image list
        // (backdrops → image URLs; frames → {frame_url, thumbnail_url} pairs).
        $isGallery = !empty($cfg['gallery']);
        $data = $isGallery
            ? $items->flatMap->apiImages()->values()
            : $items->map->toApiArray()->values();

        return response()->json([
            'status'   => true,
            'message'  => $data->isEmpty() ? 'No data found for this category.' : 'Data fetched successfully.',
            'category' => [
                'id'   => $category->id,
                'name' => $category->name,
            ] + (!empty($cfg['category_orientation']) ? ['orientation' => $category->orientation ?: 'portrait'] : []),
            'total'    => $data->count(),
            'data'     => $data,
        ]);
    }

    private function app(Request $request): ?MobileApp
    {
        $platform = $request->input('platform');

        return MobileApp::where('package_name', trim((string) $request->input('package_name')))
            // When the app sends its platform, match that exact build; the same
            // package may be registered separately for Android and iOS.
            ->when(in_array($platform, ['android', 'ios'], true), fn ($q) => $q->where('platform', $platform))
            ->where('status', true)
            ->first();
    }

    private function validate(Request $request, array $rules): ?JsonResponse
    {
        $v = Validator::make($request->all(), $rules);

        return $v->fails() ? $this->fail($v->errors()->first(), 422) : null;
    }

    private function fail(string $message, int $code): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message, 'data' => []], $code);
    }
}

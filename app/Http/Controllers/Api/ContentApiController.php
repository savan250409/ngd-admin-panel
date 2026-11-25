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
 *   /api/v1/{module}/getCategories        body: package_name
 *   /api/v1/{module}/get...ByCategory     body: package_name, category_id
 * Only data belonging to the app with that package name is ever returned.
 */
class ContentApiController extends Controller
{
    public function categories(Request $request, string $module): JsonResponse
    {
        $cfg = ModuleRegistry::get($module);

        if ($error = $this->validate($request, ['package_name' => 'required|string|max:191'])) {
            return $error;
        }
        if (!$app = $this->app($request)) {
            return $this->fail('Invalid package name.', 404);
        }

        $categories = $cfg['category_model']::query()
            ->whereHas('apps', fn ($a) => $a->where('apps.id', $app->id))
            ->active()
            ->withCount(['items as active_items_count' => fn ($q) => $q->where('status', true)])
            ->latest('id')
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
        // Gallery modules (Frame, Baby Backdrop) keep one record per category → preview its last 5 images.
        if (!empty(ModuleRegistry::get($module)['gallery'])) {
            $record = $category->items()->active()->first();

            return $record ? array_slice(array_reverse($record->apiImages()), 0, 5) : [];
        }

        return $category->items()->active()->latest('id')->limit(5)->get()
            ->map->toApiArray()->values()->all();
    }

    public function items(Request $request, string $module): JsonResponse
    {
        $cfg = ModuleRegistry::get($module);

        if ($error = $this->validate($request, [
            'package_name' => 'required|string|max:191',
            'category_id'  => 'required|integer',
        ])) {
            return $error;
        }
        if (!$app = $this->app($request)) {
            return $this->fail('Invalid package name.', 404);
        }

        $category = $cfg['category_model']::query()
            ->where('id', $request->integer('category_id'))
            ->whereHas('apps', fn ($a) => $a->where('apps.id', $app->id))
            ->active()
            ->first();
        if (!$category) {
            return $this->fail('Category not found for this package.', 404);
        }

        $items = $category->items()->active()->latest('id')->get();

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
        return MobileApp::where('package_name', trim($request->input('package_name')))->where('status', true)->first();
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use App\Support\Media;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** One controller serves the Category screen of every module (name + image + app). */
class CategoryController extends Controller
{
    use ListsRecords;

    public function index(Request $request, string $module): View
    {
        $cfg   = ModuleRegistry::get($module);
        $query = $cfg['category_model']::query()->with('apps')->withCount('items')->ordered();

        if (($s = $this->searchTerm($request)) !== '') {
            $query->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%");
                if (ctype_digit($s)) {
                    $w->orWhere('id', (int) $s);
                }
            });
        }
        if ($request->filled('app_id')) {
            $query->whereHas('apps', fn ($a) => $a->where('apps.id', $request->integer('app_id')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return view('categories.index', [
            'cfg'        => $cfg,
            'module'     => $module,
            'categories' => $query->paginate($this->perPage($request))->withQueryString(),
            'apps'       => MobileApp::orderBy('app_name')->get(),
            // For the "Index" (reorder) popup — the whole ordered list.
            'allOrdered' => $cfg['category_model']::ordered()->get(['id', 'name', 'image']),
        ]);
    }

    /** Save the category order from the Index popup (sort_order = position, 1-based). */
    public function reorder(Request $request, string $module): JsonResponse
    {
        $cfg = ModuleRegistry::get($module);
        $ids = $request->input('order', []);
        abort_unless(is_array($ids), 422);

        $position = 1;
        foreach ($ids as $id) {
            $cfg['category_model']::where('id', (int) $id)->update(['sort_order' => $position++]);
        }

        return response()->json(['success' => true]);
    }

    public function create(string $module): View
    {
        $cfg = ModuleRegistry::get($module);

        return view('categories.form', [
            'cfg'      => $cfg,
            'module'   => $module,
            'category' => new $cfg['category_model'](['status' => true, 'orientation' => 'portrait']),
            'apps'     => MobileApp::orderBy('app_name')->get(),
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $cfg  = ModuleRegistry::get($module);
        $data = $this->validated($request, $cfg, null);

        $category = new $cfg['category_model']($data);
        $category->image = Media::store($request->file('image'), "{$module}/" . Media::folder($category->name));
        $category->save();
        $category->apps()->sync($request->input('app_ids', []));

        return redirect()->route('categories.index', $module)->with('success', 'Category added successfully.');
    }

    public function edit(string $module, int $id): View
    {
        $cfg = ModuleRegistry::get($module);

        return view('categories.form', [
            'cfg'      => $cfg,
            'module'   => $module,
            'category' => $cfg['category_model']::with('apps')->findOrFail($id),
            'apps'     => MobileApp::orderBy('app_name')->get(),
        ]);
    }

    public function update(Request $request, string $module, int $id): RedirectResponse
    {
        $cfg      = ModuleRegistry::get($module);
        $category = $cfg['category_model']::findOrFail($id);

        $oldFolder   = $category->folder();                 // old category name → folder
        $oldImageRel = $category->imageRelPath();           // old cover path

        $category->fill($this->validated($request, $cfg, $category->id));
        $newFolder = $category->folder();                   // new category name → folder

        // Renamed → move the whole category folder so every file (cover + items) follows.
        if ($newFolder !== $oldFolder) {
            Media::moveDir("upload/{$module}/{$oldFolder}", "upload/{$module}/{$newFolder}");
            if ($module === 'video-template') {
                \App\Models\VideoTemplate::where('category_id', $category->id)->get()->each(function ($vt) use ($oldFolder, $newFolder) {
                    if ($vt->extract_dir) {
                        $vt->update(['extract_dir' => str_replace("/{$oldFolder}/", "/{$newFolder}/", $vt->extract_dir)]);
                    }
                });
            }
            $oldImageRel = $category->imageRelPath(); // now points into the new folder
        }

        if ($request->hasFile('image')) {
            Media::delete($oldImageRel);
            $category->image = Media::store($request->file('image'), "{$module}/{$newFolder}");
        }
        $category->save();
        $category->apps()->sync($request->input('app_ids', []));

        return redirect()->route('categories.index', $module)->with('success', 'Category updated successfully.');
    }

    public function destroy(string $module, int $id): RedirectResponse
    {
        $cfg      = ModuleRegistry::get($module);
        $category = $cfg['category_model']::withCount('items')->findOrFail($id);

        $count = $category->items_count;
        // Cascade: remove every item of this category, then its files, pivot and the row.
        $category->items()->delete();
        $category->apps()->detach();
        Media::deleteDir('upload/' . $module . '/' . $category->folder()); // cover + all item files
        $category->delete();

        $suffix = $count > 0 ? " and its {$count} " . strtolower($count === 1 ? $cfg['item_singular'] : $cfg['item_title']) : '';

        return back()->with('success', "Category{$suffix} deleted successfully.");
    }

    public function toggle(string $module, int $id): JsonResponse
    {
        $cfg      = ModuleRegistry::get($module);
        $category = $cfg['category_model']::findOrFail($id);
        $category->status = !$category->status;
        $category->save();

        return response()->json(['success' => true, 'value' => $category->status]);
    }

    private function validated(Request $request, array $cfg, ?int $ignoreId): array
    {
        $rules = [
            'app_ids'   => 'required|array|min:1',
            'app_ids.*' => 'integer|exists:apps,id',
            'name'      => 'required|string|max:150',
            'image'     => ($ignoreId ? 'nullable' : 'required') . '|image|mimes:webp|max:5120',
            'status'    => 'nullable|boolean',
        ];
        if (!empty($cfg['category_orientation'])) {
            $rules['orientation'] = 'required|in:portrait,landscape';
        }

        $data = $request->validate($rules, [
            'app_ids.required' => 'Please select at least one app.',
            'app_ids.min'      => 'Please select at least one app.',
            'image.mimes'      => 'Only .webp images are allowed.',
            'image.image'      => 'Only .webp images are allowed.',
        ]);

        // Within any single selected app, category names stay unique.
        $this->ensureNameFreeForApps($request, $cfg, $ignoreId);

        $clean = ['name' => $data['name'], 'status' => $request->boolean('status')];
        if (!empty($cfg['category_orientation'])) {
            $clean['orientation'] = $data['orientation'];
        }

        return $clean;
    }

    /** Reject a name already used by another category that shares one of the chosen apps. */
    private function ensureNameFreeForApps(Request $request, array $cfg, ?int $ignoreId): void
    {
        $appIds = $request->input('app_ids', []);

        $clash = $cfg['category_model']::query()
            ->where('name', $request->input('name'))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereHas('apps', fn ($a) => $a->whereIn('apps.id', $appIds))
            ->with('apps')
            ->first();

        if ($clash) {
            $shared = $clash->apps->whereIn('id', $appIds)->pluck('app_name')->implode(', ');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => "\"{$clash->name}\" already exists for: {$shared}. Use a different name or app.",
            ]);
        }
    }
}

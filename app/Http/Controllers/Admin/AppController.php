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

class AppController extends Controller
{
    use ListsRecords;

    public function index(Request $request): View
    {
        $query = MobileApp::query()->latest('id');

        if (($s = $this->searchTerm($request)) !== '') {
            $query->where(fn ($w) => $w->where('app_name', 'like', "%{$s}%")->orWhere('package_name', 'like', "%{$s}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return view('apps.index', [
            'apps'           => $query->paginate($this->perPage($request))->withQueryString(),
            'categoryCounts' => $this->categoryCounts(),
            'appModules'     => $this->appModules(),
        ]);
    }

    public function create(): View
    {
        return view('apps.form', ['app' => new MobileApp(['status' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($warning = $this->duplicateWarning($request, null)) {
            return back()->withInput()->with('app_exists', $warning);
        }

        MobileApp::create($data);

        return redirect()->route('apps.index')->with('success', 'App added successfully.');
    }

    public function edit(int $id): View
    {
        return view('apps.form', ['app' => MobileApp::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $app  = MobileApp::findOrFail($id);
        $data = $this->validated($request);

        if ($warning = $this->duplicateWarning($request, $app->id)) {
            return back()->withInput()->with('app_exists', $warning);
        }

        $app->update($data);

        return redirect()->route('apps.index')->with('success', 'App updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $app = MobileApp::findOrFail($id);

        // Cascade: for every module, detach this app from its categories. A category
        // left with no apps is exclusive to this app → delete it with its items + files.
        $removed = 0;
        foreach (ModuleRegistry::all() as $module => $cfg) {
            $categories = $cfg['category_model']::whereHas('apps', fn ($a) => $a->where('apps.id', $app->id))->get();
            foreach ($categories as $category) {
                $category->apps()->detach($app->id);
                if ($category->apps()->count() === 0) {
                    $category->items()->delete();
                    Media::deleteDir('upload/' . $module . '/' . $category->folder());
                    $category->delete();
                    $removed++;
                }
            }
        }

        $app->delete();

        $suffix = $removed > 0 ? " and {$removed} " . ($removed === 1 ? 'category' : 'categories') . ' only it used' : '';

        return back()->with('success', "App \"{$app->app_name}\"{$suffix} deleted successfully.");
    }

    public function toggle(int $id): JsonResponse
    {
        $app = MobileApp::findOrFail($id);
        $app->update(['status' => !$app->status]);

        return response()->json(['success' => true, 'value' => $app->status]);
    }

    /** [app_id => [module => ['title' => .., 'count' => n]]] — counted through the pivot. */
    private function categoryCounts(): array
    {
        $out = [];
        foreach (ModuleRegistry::all() as $key => $cfg) {
            $type   = (new $cfg['category_model'])->getMorphClass();
            $counts = \Illuminate\Support\Facades\DB::table('app_category')
                ->where('category_type', $type)
                ->selectRaw('app_id, COUNT(*) as c')
                ->groupBy('app_id')
                ->pluck('c', 'app_id');
            foreach ($counts as $appId => $c) {
                $out[$appId][$key] = ['title' => $cfg['title'], 'count' => (int) $c];
            }
        }

        return $out;
    }

    /**
     * Full breakdown for the "View all" popup:
     * [app_id => [ ['title','icon','categories'=>[['name','status'], ...]], ... ]]
     * Every module an app has categories in, with each category's name and status.
     */
    private function appModules(): array
    {
        $out = [];
        foreach (ModuleRegistry::all() as $cfg) {
            $cats = $cfg['category_model']::with('apps:id')->whereHas('apps')->orderBy('name')->get();
            foreach ($cats as $cat) {
                foreach ($cat->apps as $app) {
                    $out[$app->id][$cfg['title']] ??= ['title' => $cfg['title'], 'icon' => $cfg['icon'], 'categories' => []];
                    $out[$app->id][$cfg['title']]['categories'][] = ['name' => $cat->name, 'status' => (bool) $cat->status];
                }
            }
        }

        // Drop the module-title keys so each app maps to a plain ordered list.
        return array_map('array_values', $out);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'app_name'     => 'required|string|max:150',
            'package_name' => [
                'required', 'string', 'max:191',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/',
            ],
            'platform'     => ['required', Rule::in(['android', 'ios'])],
            'status'       => 'nullable|boolean',
        ], [
            'package_name.regex' => 'Package name must look like com.company.app',
            'platform.required'  => 'Choose a platform.',
            'platform.in'        => 'Platform must be Android or iOS.',
        ]);
        $data['status'] = $request->boolean('status');

        return $data;
    }

    /**
     * A package name may be registered once PER PLATFORM. If this exact
     * package + platform already exists (ignoring the row being edited),
     * return a warning message to show as a SweetAlert; the app is not saved.
     * The same package with a DIFFERENT platform is allowed and returns null.
     */
    private function duplicateWarning(Request $request, ?int $ignoreId): ?string
    {
        $package  = trim((string) $request->input('package_name'));
        $platform = $request->input('platform');

        $exists = MobileApp::where('package_name', $package)
            ->where('platform', $platform)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if (!$exists) {
            return null;
        }

        $label = $platform === 'ios' ? 'iOS' : 'Android';

        return "An app with package name \"{$package}\" already exists for {$label}. "
            . 'The same package name can be used only once per platform.';
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Models\MobileApp;
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
        ]);
    }

    public function create(): View
    {
        return view('apps.form', ['app' => new MobileApp(['status' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        MobileApp::create($this->validated($request, null));

        return redirect()->route('apps.index')->with('success', 'App added successfully.');
    }

    public function edit(int $id): View
    {
        return view('apps.form', ['app' => MobileApp::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $app = MobileApp::findOrFail($id);
        $app->update($this->validated($request, $app->id));

        return redirect()->route('apps.index')->with('success', 'App updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $app   = MobileApp::findOrFail($id);
        $total = array_sum(array_column($this->categoryCounts()[$app->id] ?? [], 'count'));

        if ($total > 0) {
            return back()->with('error', "\"{$app->app_name}\" is assigned to {$total} categories. Remove it from them first.");
        }

        $app->delete();

        return back()->with('success', 'App deleted successfully.');
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

    private function validated(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'app_name'     => 'required|string|max:150',
            'package_name' => [
                'required', 'string', 'max:191',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/',
                Rule::unique('apps', 'package_name')->ignore($ignoreId),
            ],
            'status'       => 'nullable|boolean',
        ], [
            'package_name.regex' => 'Package name must look like com.company.app',
        ]);
        $data['status'] = $request->boolean('status');

        return $data;
    }
}

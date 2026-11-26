<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Models\BaseItem;
use App\Models\MobileApp;
use App\Support\Media;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * List / create / edit / delete / toggle for a module's items.
 * Subclasses declare the module key, validation rules and how to persist a request.
 */
abstract class BaseItemController extends Controller
{
    use ListsRecords;

    protected string $module;

    /** Own columns matched by the search box (category name and #id are always searched). */
    protected array $searchColumns = [];

    /** Boolean columns that can be flipped from the list page. */
    protected array $toggleable = ['status'];

    abstract protected function rules(Request $request, ?BaseItem $item): array;

    /** Copy the validated request onto the model (handling uploads) and save it. */
    abstract protected function persist(Request $request, BaseItem $item): void;

    protected function cfg(): array
    {
        return ModuleRegistry::get($this->module);
    }

    /** Public-relative folder for a category's files: "{module}/{Category Name}[/sub]". */
    protected function dir(int $categoryId, string $sub = ''): string
    {
        $name = $this->cfg()['category_model']::find($categoryId)?->name ?? 'uncategorized';
        $path = $this->module . '/' . Media::folder($name);

        return $sub !== '' ? $path . '/' . $sub : $path;
    }

    protected function itemModel(): string
    {
        return $this->cfg()['item_model'];
    }

    protected function categoryTable(): string
    {
        return (new ($this->cfg()['category_model']))->getTable();
    }

    protected function categories(): Collection
    {
        return $this->cfg()['category_model']::with('apps')->orderBy('name')->get();
    }

    protected function render(string $view, array $data = []): View
    {
        return view("modules.{$this->module}.{$view}", $data + [
            'cfg'    => $this->cfg(),
            'module' => $this->module,
        ]);
    }

    protected function indexRoute(): string
    {
        return "{$this->module}.items.index";
    }

    public function index(Request $request): View
    {
        // Newest added OR most recently edited shows first.
        $query = $this->itemModel()::query()->with('category.apps')->orderByDesc('updated_at')->orderByDesc('id');

        if (($s = $this->searchTerm($request)) !== '') {
            $query->where(function ($w) use ($s) {
                foreach ($this->searchColumns as $col) {
                    $w->orWhere($col, 'like', "%{$s}%");
                }
                $w->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$s}%"));
                if (ctype_digit($s)) {
                    $w->orWhere('id', (int) $s);
                }
            });
        }
        if ($request->filled('app_id')) {
            $query->whereHas('category.apps', fn ($a) => $a->where('apps.id', $request->integer('app_id')));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $this->render('index', [
            'items'      => $query->paginate($this->perPage($request))->withQueryString(),
            'categories' => $this->categories(),
            'apps'       => MobileApp::orderBy('app_name')->get(),
        ] + $this->indexExtras());
    }

    protected function indexExtras(): array
    {
        return [];
    }

    public function create(): View
    {
        $model = $this->itemModel();

        return $this->render('form', ['item' => new $model(['status' => true]), 'categories' => $this->categories()] + $this->formExtras(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate($this->rules($request, null), $this->messages());
        $model = $this->itemModel();
        $this->persist($request, new $model());

        return redirect()->route($this->indexRoute())->with('success', $this->cfg()['item_singular'] . ' added successfully.');
    }

    public function edit(int $id): View
    {
        $item = $this->itemModel()::with('category')->findOrFail($id);

        return $this->render('form', ['item' => $item, 'categories' => $this->categories()] + $this->formExtras($item));
    }

    protected function formExtras(?BaseItem $item): array
    {
        return [];
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = $this->itemModel()::findOrFail($id);
        $request->validate($this->rules($request, $item), $this->messages());
        $this->persist($request, $item);

        return redirect()->route($this->indexRoute())->with('success', $this->cfg()['item_singular'] . ' updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $item = $this->itemModel()::findOrFail($id);
        $this->cleanup($item);
        $item->delete();

        return redirect()->back()->with('success', $this->cfg()['item_singular'] . ' deleted successfully.');
    }

    /** Remove every file that belongs to the record. */
    protected function cleanup(BaseItem $item): void
    {
        foreach ($item->allFileRelPaths() as $rel) {
            Media::delete($rel);
        }
    }

    /** Public-relative path of a file for a GIVEN category id (not necessarily the current one). */
    protected function catFileRel(int $categoryId, string $sub, ?string $name): ?string
    {
        return $name ? 'upload/' . $this->dir($categoryId, $sub) . '/' . $name : null;
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $field = $request->input('field', 'status');
        abort_unless(in_array($field, $this->toggleable, true), 422);

        $item = $this->itemModel()::findOrFail($id);
        $item->{$field} = !$item->{$field};
        $item->save();

        return response()->json(['success' => true, 'value' => (bool) $item->{$field}]);
    }

    /** Upload fields that must be .webp — a clear message is added for each. */
    protected function imageFields(): array
    {
        return [];
    }

    protected function messages(): array
    {
        $messages = ['category_id.required' => 'Please select a category.'];
        foreach ($this->imageFields() as $field) {
            $messages["{$field}.mimes"]      = 'Only .webp images are allowed.';
            $messages["{$field}.mimetypes"]  = 'Only .webp images are allowed.';
            $messages["{$field}.image"]      = 'Only .webp images are allowed.';
        }

        return $messages;
    }

    /** Validation rule for a required-on-create / optional-on-edit upload. */
    protected function fileRule(?BaseItem $item, string $rule): string
    {
        return ($item ? 'nullable' : 'required') . '|' . $rule;
    }

    /**
     * Handle one file column: store a new upload (deleting the old file), or — when
     * only the category changed — move the existing file into the new category folder.
     * The DB keeps only the filename. $item->category_id must already be the new one.
     */
    protected function putFile(Request $request, BaseItem $item, string $input, string $column, ?int $oldCategoryId): void
    {
        $sub    = $item->fileFields()[$column] ?? '';
        $newCat = (int) $item->category_id;

        if ($request->hasFile($input)) {
            $oldRel = $oldCategoryId ? $this->catFileRel($oldCategoryId, $sub, $item->getOriginal($column)) : null;
            $item->{$column} = Media::store($request->file($input), $this->dir($newCat, $sub));
            Media::delete($oldRel);
        } elseif ($oldCategoryId && $oldCategoryId !== $newCat && $item->{$column}) {
            Media::move(
                $this->catFileRel($oldCategoryId, $sub, $item->{$column}),
                $this->catFileRel($newCat, $sub, $item->{$column}),
            );
        }
    }

    /** category_id the item had before this request (null when creating). */
    protected function oldCategoryId(BaseItem $item): ?int
    {
        return $item->exists ? (int) $item->getOriginal('category_id') : null;
    }
}

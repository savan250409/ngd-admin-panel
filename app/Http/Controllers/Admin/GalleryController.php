<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A "gallery" module keeps ONE record per category holding an ordered list of image
 * filenames — add many at once, drag to reorder, remove individually. Frame and
 * Baby Backdrop extend this; they only set $module (and any extra validation).
 */
abstract class GalleryController extends BaseItemController
{
    /** Word for one image in this module ("frame", "backdrop", ...). */
    protected string $noun = 'image';

    protected function itemTable(): string
    {
        return (new ($this->itemModel()))->getTable();
    }

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id'  => [
                'required',
                'exists:' . $this->categoryTable() . ',id',
                Rule::unique($this->itemTable(), 'category_id')->ignore($item?->id),
            ],
            'existing'     => 'nullable|array',
            'existing.*'   => 'integer|min:0',
            'new_images'   => 'nullable|array|max:100',
            'new_images.*' => 'image|mimes:webp|max:10240',
            'status'       => 'nullable|boolean',
        ];
    }

    protected function messages(): array
    {
        return parent::messages() + [
            'category_id.unique' => "This category already has {$this->noun}s — edit that record instead.",
            'new_images.*.mimes' => 'Only .webp images are allowed.',
            'new_images.*.image' => 'Only .webp images are allowed.',
        ];
    }

    /** Gallery modules share one pair of views. */
    protected function render(string $view, array $data = []): View
    {
        return view("modules.gallery.{$view}", $data + [
            'cfg'    => $this->cfg(),
            'module' => $this->module,
            'noun'   => $this->noun,
        ]);
    }

    protected function indexExtras(): array
    {
        return [
            'totalImages' => $this->itemModel()::all(['images'])->sum(fn ($r) => count($r->images ?? [])),
        ];
    }

    protected function formExtras(?BaseItem $item): array
    {
        return [
            'usedCategoryIds' => $this->itemModel()::when($item, fn ($q) => $q->where('id', '!=', $item->id))->pluck('category_id')->all(),
        ];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        $old     = $this->oldCategoryId($item);
        $current = $item->images ?? [];
        // "existing[]" holds positions in the saved list (in the new order), never names.
        $keep = [];
        foreach ((array) $request->input('existing', []) as $idx) {
            $name = $current[(int) $idx] ?? null;
            if ($name && !in_array($name, $keep, true)) {
                $keep[] = $name;
            }
        }
        $newFiles = $request->file('new_images', []);

        if (count($keep) + count($newFiles) === 0) {
            throw ValidationException::withMessages(['new_images' => "Add at least one {$this->noun} image."]);
        }

        $newCat = $request->integer('category_id');
        $oldDir = $old ? $this->dir($old) : null;
        $newDir = $this->dir($newCat);

        // Delete removed images (from their old folder).
        foreach (array_diff($current, $keep) as $removed) {
            Media::delete($oldDir ? 'upload/' . $oldDir . '/' . $removed : null);
        }
        // Move kept images if the category changed.
        if ($old && $old !== $newCat) {
            foreach ($keep as $name) {
                Media::move('upload/' . $oldDir . '/' . $name, 'upload/' . $newDir . '/' . $name);
            }
        }
        // Store new uploads (keeping their original names) in the new folder.
        // Newest uploads go FIRST so the latest-added image shows first everywhere
        // (admin list, getCategories preview and the items API all follow this order).
        $fresh = [];
        foreach ($newFiles as $file) {
            $fresh[] = Media::store($file, $newDir);
        }

        $item->category_id = $newCat;
        $item->images      = array_values(array_merge($fresh, $keep));
        $item->status      = $request->boolean('status');
        $item->save();
    }

    protected function cleanup(BaseItem $item): void
    {
        foreach ($item->imageRelPaths() as $rel) {
            Media::delete($rel);
        }
    }
}

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
        $old      = $this->oldCategoryId($item);
        $current  = $item->images ?? [];
        $newCat   = $request->integer('category_id');
        $oldDir   = $old ? $this->dir($old) : null;
        $newDir   = $this->dir($newCat);
        $newFiles = array_values($request->file('new_images', []));

        // Build the final image list in the EXACT order shown in the editor.
        // Each entry is ['keep', savedName] or ['new', UploadedFile].
        [$entries, $kept] = $this->orderedEntries($request, $current, $newFiles);

        if (count($entries) === 0) {
            throw ValidationException::withMessages(['new_images' => "Add at least one {$this->noun} image."]);
        }

        // Delete images the user removed (from their old folder).
        foreach (array_diff($current, $kept) as $removed) {
            Media::delete($oldDir ? 'upload/' . $oldDir . '/' . $removed : null);
        }

        // Materialise the list in order: move kept files on a category change, store new uploads.
        $images = [];
        foreach ($entries as [$type, $val]) {
            if ($type === 'keep') {
                if ($old && $old !== $newCat) {
                    Media::move('upload/' . $oldDir . '/' . $val, 'upload/' . $newDir . '/' . $val);
                }
                $images[] = $val;
            } else {
                $images[] = Media::store($val, $newDir);
            }
        }

        $item->category_id = $newCat;
        $item->images      = array_values($images);
        $item->status      = $request->boolean('status');
        $item->save();
    }

    /**
     * Resolve the ordered image list the editor submitted.
     *
     * Preferred scheme — "order[]" of tokens "e:<savedIndex>" / "n:<newFileIndex>" — lets the
     * user drag existing AND new images into any sequence (that sequence is what the API returns).
     * Fallback (no JS) — "existing[]" saved indexes, with new uploads placed first.
     *
     * @return array{0: array<int, array{0: string, 1: mixed}>, 1: array<int, string>}
     */
    private function orderedEntries(Request $request, array $current, array $newFiles): array
    {
        $entries = [];
        $kept    = [];

        if ($request->has('order')) {
            foreach ((array) $request->input('order', []) as $tok) {
                if (!is_string($tok)) {
                    continue;
                }
                if (str_starts_with($tok, 'e:')) {
                    $name = $current[(int) substr($tok, 2)] ?? null;
                    if ($name !== null && !in_array($name, $kept, true)) {
                        $kept[]    = $name;
                        $entries[] = ['keep', $name];
                    }
                } elseif (str_starts_with($tok, 'n:')) {
                    $j = (int) substr($tok, 2);
                    if (isset($newFiles[$j])) {
                        $entries[] = ['new', $newFiles[$j]];
                    }
                }
            }

            return [$entries, $kept];
        }

        // Fallback: newest uploads first, then kept existing images.
        foreach ($newFiles as $file) {
            $entries[] = ['new', $file];
        }
        foreach ((array) $request->input('existing', []) as $idx) {
            $name = $current[(int) $idx] ?? null;
            if ($name !== null && !in_array($name, $kept, true)) {
                $kept[]    = $name;
                $entries[] = ['keep', $name];
            }
        }

        return [$entries, $kept];
    }

    protected function cleanup(BaseItem $item): void
    {
        foreach ($item->imageRelPaths() as $rel) {
            Media::delete($rel);
        }
    }
}
